<?php

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\AisensyAccount;
use App\Models\AisensyWaTemplate;
use App\Models\BirthdayMessageSetting;
use App\Models\BirthdayMessageStep;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiSensyService
{
    public function __construct(
        private DonationCertificateService $donationCertificateService,
        private DonationReceiptPdfService $donationReceiptPdfService,
        private BirthdayImageService $birthdayImageService,
    ) {
        $this->endpoint = (string) config('services.aisensy.endpoint', 'https://backend.aisensy.com/campaign/t1/api/v2');
    }

    private string $endpoint;

    public function sendPaymentLinkWhatsApp(DonationOrder $order): bool
    {
        $order->loadMissing('items.causeModel.aisensyAccount');

        $config = $this->resolveAccount($order);
        $apiKey = $config['key'] ?? null;
        $campaign = $config['payment_link_campaign']
            ?: config('services.aisensy.default.payment_link_campaign');
        $countryCode = $config['country_code'] ?? '91';
        $cause = $order->items->first()?->causeModel;

        if (! $apiKey || ! $campaign) {
            Log::error('AiSensy config missing (payment link)', [
                'order_id' => $order->id,
                'cause_id' => $cause?->id,
                'cause_slug' => $cause?->slug,
                'has_api_key' => filled($apiKey),
                'has_campaign' => filled($campaign),
            ]);

            return false;
        }

        if (empty($order->donor_phone) || empty($order->payment_link_url)) {
            Log::warning('Missing phone or payment link', [
                'order_id' => $order->id,
                'has_phone' => filled($order->donor_phone),
                'has_payment_link' => filled($order->payment_link_url),
            ]);

            return false;
        }

        $donorName = $order->donor_name ?: 'Donor';
        $destination = $this->formatMobile($order->donor_phone, $countryCode);

        /*
         * Campaign: payment_failed_retry_payment
         * Body: Hi {{1}} / Order {{2}} / Amount ₹{{3}} / retry link {{4}}
         */
        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaign,
            'destination' => $destination,
            'userName' => $donorName,
            'templateParams' => [
                $donorName,
                $this->paymentLinkOrderReference($order),
                NumberHelper::formatWholeAmount($order->total_amount),
                $order->payment_link_url,
            ],
            'source' => 'donation payment recovery',
            'media' => (object) [],
            'buttons' => [],
            'carouselCards' => [],
            'location' => (object) [],
            'attributes' => (object) [],
            'paramsFallbackValue' => [
                'FirstName' => $donorName,
            ],
        ];

        Log::info('AiSensy WhatsApp payment_link sending', [
            'order_id' => $order->id,
            'campaign_name' => $campaign,
            'cause_slug' => $cause?->slug,
            'template_param_count' => 4,
        ]);

        return $this->send($payload, 'payment_link', [
            'order_id' => $order->id,
            'campaign_name' => $campaign,
            'cause_slug' => $cause?->slug,
        ]);
    }

    public function sendThankYouWhatsApp(DonationOrder $order): bool
    {
        $order->loadMissing('items.causeModel');

        if (empty($order->donor_phone)) {
            return false;
        }

        $config = $this->resolveAccount($order);
        $apiKey = $config['key'] ?? null;
        $campaign = $config['thank_you_campaign'] ?? null;
        $countryCode = $config['country_code'] ?? '91';

        if (empty($config['key']) || ! $campaign) {
            Log::error('AiSensy config missing (thank you)', ['order_id' => $order->id]);

            return false;
        }

        $message = $this->buildThankYouMessage($order, $config);

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaign,
            'destination' => $this->formatMobile($order->donor_phone, $countryCode),
            'userName' => $order->donor_name ?? 'Website Donor',
        ];

        if ($message !== null) {
            $payload['templateParams'] = [$message];
        }

        return $this->send($payload, 'thank_you', ['order_id' => $order->id]);
    }

    public function sendCertificateWhatsApp(DonationOrder $order): bool
    {
        $order->loadMissing('items.causeModel');

        if (empty($order->donor_phone)) {
            return false;
        }

        $config = $this->resolveAccount($order);
        $apiKey = $config['key'] ?? null;
        $campaign = $this->resolveCertificateCampaignName(
            $config['certificate_campaign']
                ?: config('services.aisensy.default.certificate_campaign')
        );
        $countryCode = $config['country_code'] ?? '91';
        $imageUrl = $this->donationCertificateService->whatsappMediaUrl($order)
            ?? ($config['thank_you_image'] ?? null);

        if (empty($apiKey) || ! $campaign) {
            Log::error('AiSensy config missing (certificate)', ['order_id' => $order->id]);

            return false;
        }

        $imageUrl = $this->normalizeImageUrl($imageUrl);
        $imageUrl = $this->mediaUrlWithoutCacheBuster($imageUrl);
        if (! $imageUrl) {
            Log::warning('Certificate WhatsApp skipped: no image available', ['order_id' => $order->id]);

            return false;
        }

        if (! $this->validatePublicMediaUrl($imageUrl, ['order_id' => $order->id])) {
            Log::error('Certificate WhatsApp skipped: media URL is not publicly accessible', [
                'order_id' => $order->id,
                'media_url' => $imageUrl,
            ]);

            return false;
        }

        $filename = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'certificate.jpg');
        $donorName = trim((string) ($order->donor_name ?? ''));
        if ($donorName === '') {
            $donorName = 'Donor';
        }

        /*
         * IMAGE campaigns: *_uty use 5 body params; *_new use 0.
         * Prefer the campaign name configured on the cause — do not remap.
         * Omit templateParams entirely when empty — AiSensy rejects a mismatched count.
         */
        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaign,
            'destination' => $this->formatMobile($order->donor_phone, $countryCode),
            'userName' => $donorName,
            'media' => [
                'url' => $imageUrl,
                'filename' => $filename,
            ],
            'source' => (string) config('services.aisensy.certificate_source', 'donate website certificate'),
        ];

        $templateParams = $this->certificateTemplateParamsForCampaign($order, $campaign);
        if ($templateParams !== []) {
            $payload['templateParams'] = $templateParams;
        }

        return $this->send($payload, 'certificate', ['order_id' => $order->id]);
    }

    public function sendReceiptWhatsApp(DonationOrder $order): bool
    {
        $order->loadMissing('items.causeModel');

        if (empty($order->donor_phone)) {
            return false;
        }

        $config = $this->resolveAccount($order);
        $apiKey = $config['key'] ?? null;
        $campaign = $config['receipt_campaign'] ?? null;
        $countryCode = $config['country_code'] ?? '91';

        $donorName = trim((string) ($order->donor_name ?? ''));
        if ($donorName === '') {
            $donorName = 'Donor';
        }

        // Always regenerate so the PDF DONOR line matches the form-filled full name
        // on the order (same value shown on the thank-you page).
        $pdfUrl = $this->donationReceiptPdfService->whatsappMediaUrl($order, true);

        if (empty($apiKey) || ! $campaign) {
            Log::error('AiSensy config missing (receipt)', [
                'order_id' => $order->id,
                'has_api_key' => filled($apiKey),
                'has_campaign' => filled($campaign),
                'cause_slug' => $order->items->first()?->causeModel?->slug,
            ]);

            return false;
        }

        $pdfUrl = $this->normalizeImageUrl($pdfUrl);
        if (! $pdfUrl) {
            Log::warning('Receipt WhatsApp skipped: no PDF available', ['order_id' => $order->id]);

            return false;
        }

        if (! $this->validatePublicMediaUrl($pdfUrl, ['order_id' => $order->id], ['application/pdf'])) {
            Log::error('Receipt WhatsApp skipped: PDF URL is not publicly accessible', [
                'order_id' => $order->id,
                'media_url' => $pdfUrl,
            ]);

            return false;
        }

        $filename = $this->donationReceiptPdfService->whatsappFilename($order);

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaign,
            'destination' => $this->formatMobile($order->donor_phone, $countryCode),
            'userName' => $donorName,
            // Pass the full form name (not "$FirstName") so the campaign body and
            // contact label match the thank-you page / PDF receipt donor line.
            'templateParams' => [$donorName],
            'source' => (string) config('services.aisensy.receipt_source', 'donate website receipt'),
            'media' => [
                'url' => $pdfUrl,
                'filename' => $filename,
            ],
            'buttons' => [],
            'carouselCards' => [],
            'location' => (object) [],
            'attributes' => (object) [],
            'paramsFallbackValue' => [
                'FirstName' => $donorName,
            ],
        ];

        return $this->send($payload, 'receipt', ['order_id' => $order->id]);
    }

    public function sendBirthdayWhatsApp(Donor $donor, ?BirthdayMessageStep $step = null): bool
    {
        if (! app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($donor->phone)) {
            return false;
        }

        $account = $this->resolveBirthdayAccount();
        $campaign = trim((string) ($step?->campaign_name ?: Setting::getValue(Setting::AISENSY_BIRTHDAY_CAMPAIGN)));

        if ($account === null || $campaign === '') {
            Log::error('AiSensy config missing (birthday)', [
                'donor_id' => $donor->id,
                'has_account' => $account !== null,
                'has_campaign' => $campaign !== '',
            ]);

            return false;
        }

        $imageUrl = $this->birthdayImageService->whatsappMediaUrl($donor);
        $imageUrl = $this->normalizeImageUrl($imageUrl);

        if (! $imageUrl) {
            Log::warning('Birthday WhatsApp skipped: no image available', ['donor_id' => $donor->id]);

            return false;
        }

        if (! $this->validatePublicMediaUrl($imageUrl, ['donor_id' => $donor->id])) {
            Log::error('Birthday WhatsApp skipped: media URL is not publicly accessible', [
                'donor_id' => $donor->id,
                'media_url' => $imageUrl,
            ]);

            return false;
        }

        $donorName = $this->birthdayImageService->donorDisplayName($donor);
        $filename = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'birthday.jpg');

        $payload = [
            'apiKey' => $account->api_key,
            'campaignName' => $campaign,
            'destination' => $this->formatMobile((string) $donor->phone, (string) ($account->country_code ?: '91')),
            'userName' => $donorName,
            'templateParams' => [$donorName],
            'media' => [
                'url' => $imageUrl,
                'filename' => $filename,
            ],
        ];

        return $this->send($payload, 'birthday', ['donor_id' => $donor->id]);
    }

    public function sendBirthdayMarketingWhatsApp(Donor $donor, BirthdayMessageStep $step): bool
    {
        if (! app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($donor->phone)) {
            return false;
        }

        $account = $this->resolveBirthdayAccount();
        $campaign = trim((string) $step->campaign_name);

        if ($account === null || $campaign === '') {
            Log::error('AiSensy config missing (birthday marketing)', [
                'donor_id' => $donor->id,
                'step_id' => $step->id,
                'has_account' => $account !== null,
                'has_campaign' => $campaign !== '',
            ]);

            return false;
        }

        $donorName = $this->birthdayImageService->donorDisplayName($donor);
        $daysAway = max(0, (int) $step->days_before);
        // Reminder (days > 0): {{1}} name, {{2}} days. Birthday-day marketing: {{1}} name only.
        $templateParams = $daysAway > 0
            ? [$donorName, (string) $daysAway]
            : [$donorName];

        // Media optional — AiSensy campaign templates usually already include the header image.
        $media = null;
        $imageUrl = $this->normalizeImageUrl($step->publicImageUrl());

        if ($imageUrl && $this->validatePublicMediaUrl($imageUrl, ['donor_id' => $donor->id, 'step_id' => $step->id])) {
            $media = [
                'url' => $imageUrl,
                'filename' => basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'birthday-marketing.jpg'),
            ];
        }

        return $this->sendApiCampaign(
            $account,
            $campaign,
            $this->formatMobile((string) $donor->phone, (string) ($account->country_code ?: '91')),
            $donorName,
            $templateParams,
            $media,
        );
    }

    /**
     * Warm birthday wish (day 0 after paid donation). Campaign name comes from admin step.
     * Template has no body variables — empty templateParams; no personalized image required.
     */
    public function sendBirthdayWarmWishWhatsApp(Donor $donor, BirthdayMessageStep $step): bool
    {
        if (! app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($donor->phone)) {
            return false;
        }

        $account = $this->resolveBirthdayAccount();
        $campaign = trim((string) $step->campaign_name);

        if ($account === null || $campaign === '') {
            Log::error('AiSensy config missing (birthday warm wish)', [
                'donor_id' => $donor->id,
                'step_id' => $step->id,
                'has_account' => $account !== null,
                'has_campaign' => $campaign !== '',
            ]);

            return false;
        }

        $donorName = $this->birthdayImageService->donorDisplayName($donor);

        return $this->sendApiCampaign(
            $account,
            $campaign,
            $this->formatMobile((string) $donor->phone, (string) ($account->country_code ?: '91')),
            $donorName,
            [],
            null,
        );
    }

    /**
     * Generic Live API campaign send used by portal audience campaigns.
     * Does not alter transactional thank-you / certificate / payment-link flows.
     *
     * @param  list<string>  $templateParams
     * @param  array{url?: string, filename?: string}|null  $media
     * @param  list<array{type?: string, sub_type?: string, index?: int|string, parameters?: list<string>}>  $buttons
     */
    public function sendApiCampaign(
        AisensyAccount $account,
        string $campaignName,
        string $destination,
        string $userName,
        array $templateParams = [],
        ?array $media = null,
        array $buttons = [],
    ): bool {
        $apiKey = trim((string) $account->api_key);
        $campaignName = trim($campaignName);

        if ($apiKey === '' || $campaignName === '') {
            Log::error('AiSensy config missing (api campaign)', [
                'account_id' => $account->id,
            ]);

            return false;
        }

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaignName,
            'destination' => $this->formatMobile($destination, (string) ($account->country_code ?: '91')),
            'userName' => $userName !== '' ? $userName : 'Donor',
        ];

        if ($templateParams !== []) {
            $payload['templateParams'] = array_values(array_map(
                static fn ($value): string => (string) $value,
                $templateParams,
            ));
        }

        if (is_array($media) && filled($media['url'] ?? null)) {
            $payload['media'] = [
                'url' => (string) $media['url'],
                'filename' => (string) ($media['filename'] ?? 'media.jpg'),
            ];
        }

        if ($buttons !== []) {
            $payload['buttons'] = array_values(array_map(
                fn (array $button): array => $this->buttonPayload(
                    $button['parameters'] ?? [],
                    (int) ($button['index'] ?? 0),
                    (string) ($button['sub_type'] ?? 'url'),
                ),
                $buttons,
            ));
        }

        return $this->send($payload, 'api_campaign', [
            'account_id' => $account->id,
            'campaign_name' => $campaignName,
        ]);
    }

    /**
     * Authentication (OTP) template send.
     *
     * Meta requires the code in the body variable *and* in the Copy Code button;
     * sending only one of them fails as "Required parameter is missing".
     */
    public function sendOtpCampaign(
        string $destination,
        string $userName,
        string $otp,
        ?AisensyAccount $account = null,
    ): bool {
        $apiKey = trim((string) ($account?->api_key ?: config('services.aisensy.otp.key')));
        $campaignName = trim((string) (
            Setting::getValue(Setting::AISENSY_OTP_CAMPAIGN) ?: config('services.aisensy.otp.campaign')
        ));

        if ($apiKey === '' || $campaignName === '') {
            Log::warning('AiSensy config missing (otp)', [
                'has_api_key' => $apiKey !== '',
                'has_campaign' => $campaignName !== '',
            ]);

            return false;
        }

        $countryCode = (string) ($account?->country_code ?: config('services.aisensy.otp.country_code', '91'));
        $userName = $userName !== '' ? $userName : 'Donor';

        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaignName,
            'destination' => $this->formatMobile($destination, $countryCode),
            'userName' => $userName,
            'templateParams' => [$otp],
            'source' => (string) config('services.aisensy.otp.source', 'donate website login'),
            'media' => (object) [],
            'buttons' => [$this->buttonPayload([$otp])],
            'carouselCards' => [],
            'location' => (object) [],
            'attributes' => (object) [],
            'paramsFallbackValue' => [
                'FirstName' => $userName,
            ],
        ];

        return $this->send($payload, 'otp', ['campaign_name' => $campaignName]);
    }

    /**
     * @param  list<string>  $parameters
     * @return array{type: string, sub_type: string, index: int, parameters: list<array{type: string, text: string}>}
     */
    private function buttonPayload(array $parameters, int $index = 0, string $subType = 'url'): array
    {
        return [
            'type' => 'button',
            'sub_type' => $subType,
            'index' => $index,
            'parameters' => array_values(array_map(
                static fn ($value): array => [
                    'type' => 'text',
                    'text' => (string) $value,
                ],
                $parameters,
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function send(array $payload, string $type, array $context = []): bool
    {
        $timeout = $type === 'otp' ? 8 : 10;
        $retries = $type === 'otp' ? 1 : 2;
        $templateParams = is_array($payload['templateParams'] ?? null)
            ? $payload['templateParams']
            : [];
        $logContext = [
            ...$context,
            'campaign_name' => $payload['campaignName'] ?? ($context['campaign_name'] ?? null),
            'template_param_count' => count($templateParams),
        ];

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout($timeout)
                ->retry($retries, 500, function (\Throwable $exception): bool {
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response?->status() ?? 0;

                        return $status >= 500 || $status === 429;
                    }

                    return true;
                }, throw: false)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error("AiSensy WhatsApp {$type} failed", [
                ...$logContext,
                'payload' => $this->redactedPayload($payload),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            $json = $response->json();

            Log::error("AiSensy WhatsApp {$type} failed", [
                ...$logContext,
                'http_status' => $response->status(),
                'aisensy_message' => is_array($json)
                    ? ($json['message'] ?? $json['error'] ?? null)
                    : null,
                'payload' => $this->redactedPayload($payload),
                'response' => $response->body(),
            ]);

            return false;
        }

        Log::info("AiSensy WhatsApp {$type} sent", [
            ...$logContext,
            'http_status' => $response->status(),
            'response' => $response->json(),
        ]);

        return true;
    }

    private function paymentLinkOrderReference(DonationOrder $order): string
    {
        if (filled($order->provider_order_id)) {
            $reference = (string) $order->provider_order_id;
        } elseif (filled($order->order_uuid)) {
            $reference = (string) $order->order_uuid;
        } else {
            $reference = 'ORD-'.$order->id;
        }

        return str_starts_with($reference, '#') ? $reference : '#'.$reference;
    }

    private function resolveBirthdayAccount(): ?AisensyAccount
    {
        $configuredId = BirthdayMessageSetting::current()->aisensy_account_id
            ?: Setting::getValue(Setting::AISENSY_BIRTHDAY_ACCOUNT_ID);

        if ($configuredId !== null && ctype_digit((string) $configuredId)) {
            $account = AisensyAccount::query()
                ->whereKey((int) $configuredId)
                ->where('is_active', true)
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        return AisensyAccount::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactedPayload(array $payload): array
    {
        $payload['apiKey'] = '[redacted]';
        $payload['destination'] = '[redacted]';

        if (isset($payload['templateParams']) && is_array($payload['templateParams'])) {
            $payload['templateParams'] = '[redacted]';
        }

        if (isset($payload['buttons']) && is_array($payload['buttons'])) {
            $payload['buttons'] = '[redacted]';
        }

        return $payload;
    }

    private function formatMobile(string $contact, string $countryCode): string
    {
        $mobile = preg_replace('/\D/', '', $contact);

        return strlen($mobile) === 10
            ? $countryCode.$mobile
            : $mobile;
    }

    private function resolveAccount(DonationOrder $order): array
    {
        $cause = $order->items->first()?->causeModel;

        if (! $cause || ! $cause->hasAisensy()) {
            return [
                'key' => null,
                'country_code' => null,
                'payment_link_campaign' => null,
                'thank_you_campaign' => null,
                'certificate_campaign' => null,
                'receipt_campaign' => null,
                'thank_you_image' => null,
                'thank_you_message_mode' => 'template',
                'thank_you_message_template' => null,
                'thank_you_include_name' => true,
                'thank_you_include_amount' => true,
                'thank_you_include_cause' => true,
                'thank_you_include_receipt' => false,
            ];
        }

        $account = $cause->aisensyAccount;

        return [
            'key' => $account->api_key,
            'country_code' => $account->country_code,
            'payment_link_campaign' => $cause->aisensy_payment_link_campaign,
            'thank_you_campaign' => $cause->aisensy_thank_you_campaign,
            'certificate_campaign' => $cause->aisensy_certificate_campaign,
            'receipt_campaign' => $cause->receiptCampaignName(),
            'thank_you_image' => $cause->aisensy_thank_you_image,
            'thank_you_message_mode' => $cause->aisensy_thank_you_message_mode ?: 'template',
            'thank_you_message_template' => $cause->aisensy_thank_you_message_template,
            'thank_you_include_name' => (bool) $cause->aisensy_thank_you_include_name,
            'thank_you_include_amount' => (bool) $cause->aisensy_thank_you_include_amount,
            'thank_you_include_cause' => (bool) $cause->aisensy_thank_you_include_cause,
            'thank_you_include_receipt' => (bool) $cause->aisensy_thank_you_include_receipt,
        ];
    }

    private function buildThankYouMessage(DonationOrder $order, array $config): ?string
    {
        $causeName = $order->items->first()?->causeModel?->title
            ?? ucfirst((string) ($order->items->first()?->cause ?? 'Donation'));
        $formattedAmount = NumberHelper::formatWholeAmount($order->total_amount);
        $receiptNumber = $order->receiptNumberFormatted();
        $donorName = (string) ($order->donor_name ?? 'Donor');

        $tokens = [
            'name' => $donorName,
            'full_name' => $donorName,
            'amount' => $formattedAmount,
            'cause' => $causeName,
            'receipt_number' => $receiptNumber,
        ];

        $mode = (string) ($config['thank_you_message_mode'] ?? 'template');

        $message = $mode === 'builder'
            ? $this->buildBuilderThankYouMessage($tokens, $config)
            : $this->replaceMessageTokens((string) ($config['thank_you_message_template'] ?? ''), $tokens);

        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        if ($message !== '') {
            return $message;
        }

        return $mode === 'builder'
            ? sprintf(
                'Thank you %s for donating Rs %s towards %s.',
                $tokens['name'],
                $tokens['amount'],
                $tokens['cause']
            )
            : null;
    }

    /**
     * @return list<string>
     */
    private function certificateTemplateParams(DonationOrder $order): array
    {
        $donorName = trim((string) ($order->donor_name ?? ''));
        if ($donorName === '') {
            $donorName = 'Donor';
        }

        $causeName = $order->items->first()?->causeModel?->title
            ?? ucfirst((string) ($order->items->first()?->cause ?? 'Donation'));

        $donationDate = $order->paid_at?->format('d-m-Y')
            ?? now()->format('d-m-Y');

        $certificateNumber = $order->receiptNumberFormatted();
        if ($certificateNumber === '') {
            $certificateNumber = (string) ($order->provider_order_id ?: $order->id);
        }

        return [
            $donorName,
            NumberHelper::formatWholeAmount($order->total_amount),
            $causeName,
            $donationDate,
            $certificateNumber,
        ];
    }

    /**
     * Live *_new IMAGE campaigns have 0 body variables; *_uty IMAGE campaigns have 5.
     *
     * @return list<string>
     */
    private function certificateTemplateParamsForCampaign(DonationOrder $order, string $campaign): array
    {
        if ($this->certificateCampaignUsesBodyParams($campaign)) {
            return $this->certificateTemplateParams($order);
        }

        return [];
    }

    /**
     * Use the cause/default campaign name as configured.
     * Do not remap *_uty → *_new: AiSensy currently has *_uty live and *_new "Not Live".
     */
    private function resolveCertificateCampaignName(?string $configured): ?string
    {
        $configured = trim((string) $configured);
        $fallback = trim((string) config('services.aisensy.default.certificate_campaign', ''));
        $campaign = $configured !== '' ? $configured : $fallback;

        return $campaign !== '' ? $campaign : null;
    }

    private function certificateCampaignUsesBodyParams(string $campaign): bool
    {
        if (str_ends_with($campaign, '_new')) {
            return false;
        }

        if (! Schema::hasTable('aisensy_wa_templates')) {
            return str_ends_with($campaign, '_uty');
        }

        $paramCount = AisensyWaTemplate::query()
            ->where(function ($query) use ($campaign): void {
                $query->where('live_campaign_name', $campaign)
                    ->orWhere('name', $campaign);
            })
            ->where('is_active', true)
            ->value('param_count');

        if ($paramCount === null) {
            return str_ends_with($campaign, '_uty');
        }

        return (int) $paramCount > 0;
    }

    /**
     * AiSensy media fetch can fail on cache-buster query strings.
     */
    private function mediaUrlWithoutCacheBuster(?string $imageUrl): ?string
    {
        if (! is_string($imageUrl) || $imageUrl === '') {
            return null;
        }

        $parts = parse_url($imageUrl);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $imageUrl;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';

        return $parts['scheme'].'://'.$parts['host'].$port.$path;
    }

    private function replaceMessageTokens(string $template, array $tokens): string
    {
        if (trim($template) === '') {
            return '';
        }

        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }

    private function buildBuilderThankYouMessage(array $tokens, array $config): string
    {
        $parts = [];

        if ((bool) ($config['thank_you_include_name'] ?? false)) {
            $parts[] = 'Thank you '.$tokens['name'];
        } else {
            $parts[] = 'Thank you';
        }

        if ((bool) ($config['thank_you_include_amount'] ?? false)) {
            $parts[] = 'for donating Rs '.$tokens['amount'];
        } else {
            $parts[] = 'for your contribution';
        }

        if ((bool) ($config['thank_you_include_cause'] ?? false)) {
            $parts[] = 'towards '.$tokens['cause'];
        }

        if ((bool) ($config['thank_you_include_receipt'] ?? false)) {
            $parts[] = 'Receipt: '.$tokens['receipt_number'];
        }

        return implode(' ', array_values(array_filter($parts, fn (?string $part): bool => trim((string) $part) !== ''))).'.';
    }

    private function normalizeImageUrl(?string $imageUrl): ?string
    {
        if (! $imageUrl) {
            return null;
        }

        if (str_starts_with($imageUrl, 'http://')) {
            $imageUrl = 'https://'.substr($imageUrl, 7);
        }

        if (str_starts_with($imageUrl, 'https://')) {
            return $imageUrl;
        }

        $url = asset(ltrim($imageUrl, '/'));

        return str_starts_with($url, 'http://')
            ? 'https://'.substr($url, 7)
            : $url;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $allowedContentTypePrefixes
     */
    private function validatePublicMediaUrl(
        string $imageUrl,
        array $context = [],
        array $allowedContentTypePrefixes = ['image/'],
    ): bool {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'SadbhavnaDonationCertificate/1.0'])
                ->head($imageUrl);

            if (! $response->successful()) {
                $response = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'SadbhavnaDonationCertificate/1.0'])
                    ->get($imageUrl);
            }

            if (! $response->successful()) {
                Log::warning('WhatsApp media URL check failed', [
                    ...$context,
                    'status' => $response->status(),
                    'media_url' => $imageUrl,
                ]);

                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            if ($contentType !== '') {
                $allowed = false;

                foreach ($allowedContentTypePrefixes as $prefix) {
                    if (str_starts_with($contentType, $prefix)) {
                        $allowed = true;
                        break;
                    }
                }

                if (! $allowed) {
                    Log::warning('WhatsApp media URL returned unexpected content type', [
                        ...$context,
                        'content_type' => $contentType,
                        'media_url' => $imageUrl,
                    ]);

                    return false;
                }
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('WhatsApp media URL check threw an exception', [
                ...$context,
                'media_url' => $imageUrl,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
