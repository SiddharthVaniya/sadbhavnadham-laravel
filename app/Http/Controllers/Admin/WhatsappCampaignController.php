<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsappCampaignRunJob;
use App\Models\AisensyAccount;
use App\Models\AisensyWaTemplate;
use App\Models\Cause;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCampaignRun;
use App\Services\AiSensy\AiSensyProjectClient;
use App\Services\DonationAttributionService;
use App\Services\WhatsappCampaignDeliverySync;
use App\Services\WhatsappCampaignLauncher;
use App\Support\AdminInertiaResources;
use App\Support\DonorAudienceQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappCampaignController extends Controller
{
    public function index(): Response
    {
        $runs = WhatsappCampaignRun::query()
            ->with(['account:id,name', 'template:id,name', 'creator:id,name'])
            ->latest('id')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/WhatsappCampaigns/Index', [
            'runs' => AdminInertiaResources::paginated(
                $runs,
                fn (WhatsappCampaignRun $run) => AdminInertiaResources::whatsappCampaignRun($run)
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/WhatsappCampaigns/Create', $this->formPayload());
    }

    public function previewAudience(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $query = DonorAudienceQuery::campaignEligible($filters);

        $totalCandidates = (clone $query)->count();
        $sample = (clone $query)->limit(50)->get(['id', 'name', 'phone', 'city', 'state', 'whatsapp_opt_out']);

        $eligible = $sample->filter(fn ($donor) => DonorAudienceQuery::isSendableDonor($donor))->values();

        return response()->json([
            'candidate_count' => $totalCandidates,
            'sample_eligible_count' => $eligible->count(),
            'sample' => $eligible->take(20)->map(fn ($donor) => [
                'id' => $donor->id,
                'name' => $donor->name,
                'phone' => $donor->phone,
                'city' => $donor->city,
                'state' => $donor->state,
            ])->all(),
            'note' => 'Final audience applies phone validation on launch; preview sample is capped.',
        ]);
    }

    public function store(Request $request, WhatsappCampaignLauncher $launcher): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'aisensy_account_id' => ['required', 'integer', 'exists:aisensy_accounts,id'],
            'aisensy_wa_template_id' => ['required', 'integer', 'exists:aisensy_wa_templates,id'],
            'param_map' => ['nullable', 'array'],
            'param_map.*' => ['string', 'max:255'],
            'media' => ['nullable', 'file', 'max:16384'],
            'location' => ['nullable', 'array'],
            'location.latitude' => ['nullable', 'string', 'max:40'],
            'location.longitude' => ['nullable', 'string', 'max:40'],
            'location.name' => ['nullable', 'string', 'max:255'],
            'location.address' => ['nullable', 'string', 'max:500'],
            'dry_run' => ['sometimes', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:40'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'min_paid' => ['nullable', 'numeric', 'min:0'],
            'repeat' => ['sometimes', 'boolean'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
        ]);

        $account = AisensyAccount::query()
            ->whereKey($data['aisensy_account_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $template = AisensyWaTemplate::query()
            ->whereKey($data['aisensy_wa_template_id'])
            ->where('aisensy_account_id', $account->id)
            ->where('is_active', true)
            ->firstOrFail();

        $media = null;
        $location = null;

        if ($template->requiresMediaHeader()) {
            $request->validate([
                'media' => [
                    'required',
                    'file',
                    'max:16384',
                    match ($template->normalizedHeaderType()) {
                        AisensyWaTemplate::HEADER_VIDEO => 'mimetypes:video/mp4,video/3gpp',
                        AisensyWaTemplate::HEADER_DOCUMENT => 'mimetypes:application/pdf',
                        default => 'mimetypes:image/jpeg,image/png,image/webp',
                    },
                ],
            ]);

            $file = $request->file('media');
            $path = $file->store('whatsapp-campaigns/media', 'public');
            $media = [
                'path' => $path,
                'filename' => $file->getClientOriginalName() ?: basename($path),
            ];
        }

        if ($template->requiresLocationHeader()) {
            $locationData = $request->validate([
                'location.latitude' => ['required', 'string', 'max:40'],
                'location.longitude' => ['required', 'string', 'max:40'],
                'location.name' => ['nullable', 'string', 'max:255'],
                'location.address' => ['nullable', 'string', 'max:500'],
            ])['location'];

            $location = [
                'latitude' => (string) $locationData['latitude'],
                'longitude' => (string) $locationData['longitude'],
                'name' => (string) ($locationData['name'] ?? ''),
                'address' => (string) ($locationData['address'] ?? ''),
            ];
        }

        $run = $launcher->launch(
            user: $request->user(),
            account: $account,
            name: $data['name'],
            template: $template,
            filters: $this->filtersFromRequest($request),
            paramMap: array_values($data['param_map'] ?? []),
            dryRun: $request->boolean('dry_run'),
            media: $media,
            location: $location,
        );

        return redirect()
            ->route('admin.whatsapp-campaigns.show', $run)
            ->with('status', $request->boolean('dry_run')
                ? 'Dry run saved. No WhatsApp messages were sent.'
                : 'Campaign queued. Messages will send in the background.');
    }

    public function show(WhatsappCampaignRun $whatsapp_campaign): Response
    {
        $whatsapp_campaign->load(['account:id,name', 'template:id,name,param_count', 'creator:id,name']);

        $failed = $whatsapp_campaign->recipients()
            ->with('donor:id,name,phone')
            ->where('status', 'failed')
            ->latest('id')
            ->limit(50)
            ->get();

        return Inertia::render('Admin/WhatsappCampaigns/Show', [
            'run' => AdminInertiaResources::whatsappCampaignRun($whatsapp_campaign, detailed: true),
            'failedRecipients' => $failed->map(fn ($row) => [
                'id' => $row->id,
                'donor_name' => $row->donor?->name,
                'phone' => $row->phone,
                'error' => $row->error,
            ])->all(),
        ]);
    }

    public function cancel(WhatsappCampaignRun $whatsapp_campaign): RedirectResponse
    {
        if (! $whatsapp_campaign->isCancellable()) {
            return back()->with('status', 'This campaign can no longer be cancelled.');
        }

        $whatsapp_campaign->forceFill([
            'status' => WhatsappCampaignRun::STATUS_CANCELLED,
            'finished_at' => now(),
        ])->save();

        return back()->with('status', 'Campaign cancelled. Pending sends will stop.');
    }

    public function retryFailed(WhatsappCampaignRun $whatsapp_campaign): RedirectResponse
    {
        if ($whatsapp_campaign->status === WhatsappCampaignRun::STATUS_CANCELLED) {
            return back()->with('status', 'Cancelled campaigns cannot retry failed recipients.');
        }

        $failed = $whatsapp_campaign->recipients()
            ->where('status', WhatsappCampaignRecipient::STATUS_FAILED)
            ->get();

        if ($failed->isEmpty()) {
            return back()->with('status', 'No failed recipients to retry.');
        }

        foreach ($failed as $recipient) {
            $recipient->forceFill([
                'status' => WhatsappCampaignRecipient::STATUS_PENDING,
                'error' => null,
                'sent_at' => null,
            ])->save();
        }

        WhatsappCampaignRun::query()->whereKey($whatsapp_campaign->id)->update([
            'failed_count' => 0,
            'status' => WhatsappCampaignRun::STATUS_QUEUED,
            'finished_at' => null,
            'last_error' => null,
        ]);

        ProcessWhatsappCampaignRunJob::dispatch($whatsapp_campaign->id);

        return back()->with('status', "Queued retry for {$failed->count()} failed recipient(s).");
    }

    public function syncDelivery(WhatsappCampaignRun $whatsapp_campaign, WhatsappCampaignDeliverySync $sync): RedirectResponse
    {
        try {
            $stats = $sync->sync($whatsapp_campaign->fresh());
        } catch (\Throwable $e) {
            toastr()->warning($e->getMessage());

            return back()
                ->with('status', $e->getMessage())
                ->with('flash_tone', 'warning');
        }

        $message = "AiSensy delivery synced — sent {$stats['sent']}, delivered {$stats['delivered']}, read {$stats['read']}, failed {$stats['failed']}.";
        toastr()->success($message);

        return back()->with('status', $message);
    }

    public function syncTemplates(Request $request, AiSensyProjectClient $client): RedirectResponse
    {
        $data = $request->validate([
            'aisensy_account_id' => ['required', 'integer', 'exists:aisensy_accounts,id'],
        ]);

        $account = AisensyAccount::query()->whereKey($data['aisensy_account_id'])->firstOrFail();

        if (! $account->hasProjectApiPassword()) {
            return back()->with('status', 'Add a Project API password on AiSensy Accounts → Edit account first, then sync again.');
        }

        if (blank($account->project_id)) {
            return back()->with('status', 'Add Project ID on AiSensy Accounts → Edit account first, then sync again.');
        }

        $count = $client->syncApprovedTemplates($account);

        if ($count === 0) {
            $detail = $client->lastError() ?: 'No APPROVED templates returned.';

            return back()->with(
                'status',
                "Sync got 0 templates. {$detail} Use “Add template mapping” with the exact Meta template name from AiSensy → Manage → Template Message."
            );
        }

        return back()->with(
            'status',
            "Synced {$count} approved template(s) from AiSensy (direct list or templates used on existing campaigns)."
        );
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aisensy_account_id' => ['required', 'integer', 'exists:aisensy_accounts,id'],
            'name' => ['required', 'string', 'max:255'],
            'live_campaign_name' => ['nullable', 'string', 'max:255'],
            'header_type' => ['nullable', 'string', 'in:'.implode(',', AisensyWaTemplate::headerTypes())],
            'param_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'body_preview' => ['nullable', 'string', 'max:2000'],
        ]);

        AisensyWaTemplate::query()->create([
            'aisensy_account_id' => $data['aisensy_account_id'],
            'external_id' => 'manual:'.uniqid(),
            'name' => $data['name'],
            'live_campaign_name' => $data['live_campaign_name'] ?: $data['name'],
            'header_type' => $data['header_type'] ?? AisensyWaTemplate::HEADER_TEXT,
            'status' => 'APPROVED',
            'param_count' => (int) ($data['param_count'] ?? 0),
            'body_preview' => $data['body_preview'] ?? null,
            'is_manual' => true,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        return back()->with('status', 'Template saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        $accounts = AisensyAccount::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'project_id', 'project_api_password']);

        $templates = AisensyWaTemplate::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('status')->orWhereRaw('UPPER(status) = ?', ['APPROVED']);
            })
            ->orderBy('name')
            ->get();

        return [
            'accounts' => $accounts->map(fn (AisensyAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'has_project_api_password' => $account->hasProjectApiPassword(),
                'has_project_id' => filled($account->project_id),
            ])->all(),
            'templates' => $templates->map(fn (AisensyWaTemplate $template) => [
                'id' => $template->id,
                'aisensy_account_id' => $template->aisensy_account_id,
                'name' => $template->name,
                'live_campaign_name' => $template->resolvedCampaignName(),
                'header_type' => $template->normalizedHeaderType(),
                'requires_media' => $template->requiresMediaHeader(),
                'requires_location' => $template->requiresLocationHeader(),
                'param_count' => $template->param_count,
                'body_preview' => $template->body_preview,
                'is_manual' => $template->is_manual,
            ])->all(),
            'causes' => Cause::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'title']),
            'sourceOptions' => collect(DonationAttributionService::trafficSourceOptions())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'paramOptions' => [
                ['value' => 'donor.name', 'label' => 'Donor name'],
                ['value' => 'donor.city', 'label' => 'Donor city'],
                ['value' => 'donor.state', 'label' => 'Donor state'],
                ['value' => 'donor.email', 'label' => 'Donor email'],
                ['value' => 'donor.phone', 'label' => 'Donor phone'],
                ['value' => 'last_paid_amount', 'label' => 'Last paid amount'],
            ],
            'maxAudience' => (int) config('services.aisensy.campaign_max_audience', 10000),
            'headerTypes' => AisensyWaTemplate::headerTypes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'search' => $request->input('search'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'source' => $request->input('source'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'min_paid' => $request->input('min_paid'),
            'repeat' => $request->boolean('repeat') ? 1 : null,
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_content' => $request->input('utm_content'),
            'cause_id' => $request->input('cause_id'),
        ];
    }
}
