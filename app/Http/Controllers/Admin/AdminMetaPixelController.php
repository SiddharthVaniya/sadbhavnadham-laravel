<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaCapiEventLog;
use App\Models\MetaPixel;
use App\Support\Meta\MetaCapiPixelRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMetaPixelController extends Controller
{
    public function index(MetaCapiPixelRegistry $registry): Response
    {
        $envPixelIds = collect($registry->envPixelsForAdmin())->pluck('pixel_id')->all();

        $pixels = MetaPixel::query()
            ->orderBy('label')
            ->get()
            ->map(fn (MetaPixel $pixel) => $this->serializePixel(
                $pixel,
                in_array($pixel->normalizedPixelId(), $envPixelIds, true),
            ))
            ->values()
            ->all();

        $recentLogs = MetaCapiEventLog::query()
            ->with(['pixel:id,label,pixel_id', 'donationOrder:id,order_uuid'])
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (MetaCapiEventLog $log) => [
                'id' => $log->id,
                'pixel_label' => $log->pixel?->label,
                'pixel_id' => $log->pixel?->pixel_id,
                'event_name' => $log->event_name,
                'event_id' => $log->event_id,
                'status' => $log->status,
                'http_status' => $log->http_status,
                'error_message' => $log->error_message,
                'order_uuid' => $log->donationOrder?->order_uuid,
                'sent_at' => $log->sent_at?->timezone(config('app.timezone'))->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('Admin/Meta/Pixels', [
            'envPixels' => $registry->envPixelsForAdmin(),
            'allowDatabasePixels' => (bool) config('meta_capi.allow_database_pixels', false),
            'capiEnabled' => (bool) config('meta_capi.enabled', true),
            'pixels' => $pixels,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('meta_capi.allow_database_pixels', false)) {
            return redirect()
                ->route('admin.meta.pixels')
                ->with('error', 'Database pixels are disabled. Configure META_CAPI_* in .env instead.');
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'pixel_id' => ['required', 'string', 'max:32'],
            'access_token' => ['required', 'string', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'send_purchase' => ['sometimes', 'boolean'],
            'send_initiate_checkout' => ['sometimes', 'boolean'],
            'test_event_code' => ['nullable', 'string', 'max:64'],
        ]);

        MetaPixel::query()->create([
            'label' => trim($data['label']),
            'pixel_id' => trim($data['pixel_id']),
            'access_token' => trim($data['access_token']),
            'is_active' => $request->boolean('is_active', true),
            'send_purchase' => $request->boolean('send_purchase', true),
            'send_initiate_checkout' => $request->boolean('send_initiate_checkout', true),
            'test_event_code' => filled($data['test_event_code'] ?? null)
                ? trim((string) $data['test_event_code'])
                : null,
        ]);

        return redirect()
            ->route('admin.meta.pixels')
            ->with('status', 'Meta pixel saved.');
    }

    public function update(Request $request, MetaPixel $metaPixel): RedirectResponse
    {
        if (! config('meta_capi.allow_database_pixels', false)) {
            return redirect()
                ->route('admin.meta.pixels')
                ->with('error', 'Edit pixels in .env (META_CAPI_PIXEL_*). Database storage is disabled.');
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'pixel_id' => ['required', 'string', 'max:32'],
            'access_token' => ['nullable', 'string', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'send_purchase' => ['sometimes', 'boolean'],
            'send_initiate_checkout' => ['sometimes', 'boolean'],
            'test_event_code' => ['nullable', 'string', 'max:64'],
        ]);

        $payload = [
            'label' => trim($data['label']),
            'pixel_id' => trim($data['pixel_id']),
            'is_active' => $request->boolean('is_active', $metaPixel->is_active),
            'send_purchase' => $request->boolean('send_purchase', $metaPixel->send_purchase),
            'send_initiate_checkout' => $request->boolean('send_initiate_checkout', $metaPixel->send_initiate_checkout),
            'test_event_code' => filled($data['test_event_code'] ?? null)
                ? trim((string) $data['test_event_code'])
                : null,
        ];

        $accessToken = trim((string) ($data['access_token'] ?? ''));

        if ($accessToken !== '' && $accessToken !== '********') {
            $payload['access_token'] = $accessToken;
        }

        if (! isset($payload['access_token']) && ! $metaPixel->hasAccessToken()) {
            return back()->withErrors(['access_token' => 'Access token is required.'])->withInput();
        }

        $metaPixel->update($payload);

        return redirect()
            ->route('admin.meta.pixels')
            ->with('status', 'Meta pixel updated.');
    }

    public function destroy(MetaPixel $metaPixel): RedirectResponse
    {
        $metaPixel->delete();

        return redirect()
            ->route('admin.meta.pixels')
            ->with('status', 'Meta pixel removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePixel(MetaPixel $pixel, bool $managedByEnv = false): array
    {
        return [
            'id' => $pixel->id,
            'label' => $pixel->label,
            'pixel_id' => $pixel->pixel_id,
            'is_active' => (bool) $pixel->is_active,
            'send_purchase' => (bool) $pixel->send_purchase,
            'send_initiate_checkout' => (bool) $pixel->send_initiate_checkout,
            'test_event_code' => $pixel->test_event_code,
            'has_access_token' => $managedByEnv || $pixel->hasAccessToken(),
            'credentials_source' => $managedByEnv ? 'env' : 'database',
            'managed_by_env' => $managedByEnv,
            'last_event_at' => $pixel->last_event_at?->timezone(config('app.timezone'))->toDateTimeString(),
            'last_event_status' => $pixel->last_event_status,
            'last_event_error' => $pixel->last_event_error,
        ];
    }
}
