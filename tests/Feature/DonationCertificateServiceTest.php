<?php

use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\Setting;
use App\Services\DonationCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeCertificateOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_cert_'.uniqid(),
        'donor_name' => 'વિજયભાઈ ડોબરીયા',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::create(2026, 5, 26),
    ], $overrides));
}

function attachCauseToOrder(DonationOrder $order, Cause $cause): void
{
    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause' => $cause->title,
        'title' => $cause->title,
        'quantity' => 1,
        'unit_amount' => $order->total_amount,
        'amount' => $order->total_amount,
    ]);
}

function createCertificateTemplateImage(string $relativePath, int $width = 400, int $height = 560): void
{
    if (! extension_loaded('gd')) {
        test()->markTestSkipped('GD extension is required for certificate template tests.');
    }

    $absolutePath = Storage::disk('public')->path($relativePath);
    File::ensureDirectoryExists(dirname($absolutePath));

    $image = imagecreatetruecolor($width, $height);
    imagejpeg($image, $absolutePath, 90);
    imagedestroy($image);
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('donation.certificate.enabled', true);

    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => '1', 'label' => 'Send Donation Certificate on WhatsApp', 'group' => 'notifications'],
    );
});

it('formats donor name without adding a prefix', function () {
    $order = new DonationOrder([
        'donor_name' => 'વિજયભાઈ ડોબરીયા',
    ]);

    expect(app(DonationCertificateService::class)->donorDisplayName($order))
        ->toBe('વિજયભાઈ ડોબરીયા');
});

it('trims leading trailing and duplicate spaces from donor name', function () {
    $order = new DonationOrder([
        'donor_name' => '  test   donor  ',
    ]);

    expect(app(DonationCertificateService::class)->donorDisplayName($order))
        ->toBe('Test Donor');
});

it('title cases each word in latin donor names for the certificate', function () {
    $order = new DonationOrder([
        'donor_name' => 'monil vekariya',
    ]);

    expect(app(DonationCertificateService::class)->donorDisplayName($order))
        ->toBe('Monil Vekariya');
});

it('formats certificate date as DD-MM-YYYY with the configured prefix', function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 26, 10, 0, 0));

    $order = new DonationOrder([
        'paid_at' => now(),
    ]);

    $service = app(DonationCertificateService::class);

    expect($service->formattedDateLine($order))
        ->toBe('તારીખ : 26-05-2026')
        ->and($service->formattedDateParts($order))
        ->toBe(['તારીખ : ', '26-05-2026']);
});

it('renders certificate date with configured color in the template', function () {
    config()->set('donation.certificate.date.color', '#ffffff');

    $html = view('certificates.sanman-patra', [
        'donorName' => 'Monil Vekariya',
        'dateValue' => '06-07-2026',
        'dateLine' => 'તારીખ : 06-07-2026',
        'templateImage' => 'data:image/jpeg;base64,',
        'pageWidthPt' => 595,
        'pageHeightPt' => 842,
        'nameTopPercent' => 60.8,
        'nameSizePt' => 44,
        'nameMaxWidthPt' => 488,
        'nameWrap' => false,
        'nameColor' => '#8B1538',
        'nameFontWeight' => 'normal',
        'dateBottomPt' => 52,
        'dateBoxHeightPt' => 34,
        'dateSizePt' => 20,
        'dateColor' => '#ffffff',
    ])->render();

    expect($html)
        ->toContain('color: #ffffff')
        ->toContain('તારીખ : 06-07-2026');
});

it('renders certificate donor name with env-configured size and font weight', function () {
    config()->set('donation.certificate.name.size_pt', 56);
    config()->set('donation.certificate.name.font_weight', '700');
    config()->set('donation.certificate.name.color', '#840405');

    $service = app(DonationCertificateService::class);
    $nameConfig = (array) config('donation.certificate.name');

    $html = view('certificates.sanman-patra', [
        'donorName' => 'Pritesh Rathod',
        'dateValue' => '07-07-2026',
        'templateImage' => 'data:image/jpeg;base64,',
        'pageWidthPt' => 595,
        'pageHeightPt' => 842,
        'nameTopPercent' => (float) $nameConfig['top_percent'],
        'nameSizePt' => (float) $nameConfig['size_pt'],
        'nameMaxWidthPt' => 488,
        'nameWrap' => false,
        'nameColor' => '#840405',
        'nameFontWeight' => $service->certificateNameFontWeight($nameConfig),
        'dateBottomPt' => 52,
        'dateBoxHeightPt' => 34,
        'dateSizePt' => 20,
        'dateColor' => '#ffffff',
    ])->render();

    expect($html)
        ->toContain('font-size: 56pt')
        ->toContain('font-weight: bold')
        ->toContain('color: #840405')
        ->toContain('Pritesh Rathod');
});

it('reads certificate donor name color from env config', function () {
    config()->set('donation.certificate.name.color', '#840405');

    expect(config('donation.certificate.name.color'))->toBe('#840405');
});

it('uses donation name color default when env hex color is empty', function () {
    expect(donation_env_hex_color('DONATION_CERTIFICATE_NAME_COLOR_TEST_EMPTY_xyz', '#8B1538'))
        ->toBe('#8B1538');
});

it('keeps configured certificate name size for short donor names', function () {
    config()->set('donation.certificate.name.size_pt', 90);
    config()->set('donation.certificate.name.min_size_pt', 32);
    config()->set('donation.certificate.name.max_width_percent', 82);

    $service = app(DonationCertificateService::class);
    $layout = $service->certificateNameLayout('Pritesh Rathod', 900);

    expect($layout['size_pt'])->toBe(90.0)
        ->and($layout['wrap'])->toBeFalse();
});

it('shrinks certificate donor name font size for long names', function () {
    config()->set('donation.certificate.name.size_pt', 90);
    config()->set('donation.certificate.name.min_size_pt', 32);
    config()->set('donation.certificate.name.max_width_percent', 82);
    config()->set('donation.certificate.name.font_weight', '600');

    $service = app(DonationCertificateService::class);
    $layout = $service->certificateNameLayout('Dr Vijaybhai Manubhai Dobariya', 900);

    expect($layout['size_pt'])->toBeLessThan(90.0)
        ->and($layout['size_pt'])->toBeGreaterThanOrEqual(32.0);
});

it('wraps extremely long certificate donor names onto multiple lines', function () {
    config()->set('donation.certificate.name.size_pt', 90);
    config()->set('donation.certificate.name.min_size_pt', 32);
    config()->set('donation.certificate.name.max_width_percent', 82);

    $service = app(DonationCertificateService::class);
    $layout = $service->certificateNameLayout(
        'Shri Kantilal Babulal Dobariya Family Trust Representative',
        595,
    );

    expect($layout['size_pt'])->toBe(32.0)
        ->and($layout['wrap'])->toBeTrue();
});

it('renders wrapped long certificate donor names in the template', function () {
    $html = view('certificates.sanman-patra', [
        'donorName' => 'Very Long Donor Name Example',
        'dateValue' => '07-07-2026',
        'templateImage' => 'data:image/jpeg;base64,',
        'pageWidthPt' => 595,
        'pageHeightPt' => 842,
        'nameTopPercent' => 57,
        'nameSizePt' => 40,
        'nameMaxWidthPt' => 488,
        'nameWrap' => true,
        'nameColor' => '#840405',
        'nameFontWeight' => 'bold',
        'dateBottomPt' => 35,
        'dateBoxHeightPt' => 34,
        'dateSizePt' => 20,
        'dateColor' => '#ffffff',
    ])->render();

    expect($html)
        ->toContain('donor-name-cell--wrap')
        ->toContain('max-width: 488pt');
});

it('maps numeric certificate name font weights for dompdf', function () {
    $service = app(DonationCertificateService::class);

    expect($service->certificateNameFontWeight(['font_weight' => '400']))->toBe('normal')
        ->and($service->certificateNameFontWeight(['font_weight' => '700']))->toBe('bold')
        ->and($service->certificateNameFontWeight(['font_weight' => 'bold']))->toBe('bold')
        ->and($service->certificateNameFontWeight(['bold' => 'true']))->toBe('bold');
});

it('generates and stores a personalized certificate for a paid order', function () {
    $order = makeCertificateOrder();

    $url = app(DonationCertificateService::class)->generate($order);

    expect($url)->not->toBeNull()
        ->and(str_ends_with((string) $url, '.png'))->toBeTrue()
        ->and(str_starts_with((string) $url, 'https://'))->toBeTrue();

    expect(Storage::disk('public')->exists('certificates/sanman-'.$order->id.'.png'))->toBeTrue();
    expect(Storage::disk('public')->exists('certificates/sanman-'.$order->id.'.pdf'))->toBeFalse();
});

it('creates a whatsapp optimized jpeg for certificate media', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD extension is required for WhatsApp JPEG optimization.');
    }

    config()->set('donation.certificate.public_base_url', 'https://donate.sadbhavnadham.org');

    $order = makeCertificateOrder();

    $service = app(DonationCertificateService::class);
    $url = $service->whatsappMediaUrl($order, force: true);

    expect($url)
        ->toContain('https://donate.sadbhavnadham.org/storage/certificates/sanman-'.$order->id.'-whatsapp.jpg')
        ->and(Storage::disk('public')->exists('certificates/sanman-'.$order->id.'-whatsapp.jpg'))->toBeTrue();
});

it('removes a legacy stored pdf when regenerating a certificate', function () {
    $order = makeCertificateOrder();
    $pdfPath = 'certificates/sanman-'.$order->id.'.pdf';

    Storage::disk('public')->put($pdfPath, 'legacy-pdf');

    app(DonationCertificateService::class)->generate($order, force: true);

    expect(Storage::disk('public')->exists($pdfPath))->toBeFalse();
});

it('returns null when certificate generation is disabled', function () {
    config()->set('donation.certificate.enabled', false);

    $order = makeCertificateOrder();

    expect(app(DonationCertificateService::class)->generate($order))->toBeNull();
});

it('returns null when donation certificate setting is disabled', function () {
    Setting::query()->updateOrCreate(
        ['key' => Setting::SEND_DONATION_CERTIFICATE],
        ['value' => '0', 'label' => 'Send Donation Certificate on WhatsApp', 'group' => 'notifications'],
    );

    $order = makeCertificateOrder();

    expect(app(DonationCertificateService::class)->generate($order))->toBeNull();
});

it('uses a cause-specific certificate template when configured', function () {
    $globalTemplate = (string) config('donation.certificate.template');

    if (! File::exists($globalTemplate)) {
        $this->markTestSkipped('Global certificate template is not available.');
    }

    $globalBackup = File::get($globalTemplate);

    try {
        File::delete($globalTemplate);

        $relativePath = 'causes/certificates/custom-template.jpg';
        createCertificateTemplateImage($relativePath);

        $cause = Cause::factory()->create([
            'certificate_template' => 'storage/'.$relativePath,
        ]);

        $order = makeCertificateOrder();
        attachCauseToOrder($order, $cause);

        $url = app(DonationCertificateService::class)->generate($order, force: true);

        expect($url)->not->toBeNull()
            ->and(Storage::disk('public')->exists('certificates/sanman-'.$order->id.'.png'))->toBeTrue();
    } finally {
        File::put($globalTemplate, $globalBackup);
    }
});

it('falls back to the global certificate template when the cause template is missing', function () {
    $globalTemplate = (string) config('donation.certificate.template');

    if (! File::exists($globalTemplate)) {
        $this->markTestSkipped('Global certificate template is not available.');
    }

    $cause = Cause::factory()->create([
        'certificate_template' => 'storage/causes/certificates/missing-template.jpg',
    ]);

    $order = makeCertificateOrder();
    attachCauseToOrder($order, $cause);

    $url = app(DonationCertificateService::class)->generate($order, force: true);

    expect($url)->not->toBeNull()
        ->and(Storage::disk('public')->exists('certificates/sanman-'.$order->id.'.png'))->toBeTrue();
});
