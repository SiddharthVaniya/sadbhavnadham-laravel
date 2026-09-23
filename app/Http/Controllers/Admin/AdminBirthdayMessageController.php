<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BirthdayMessageSetting;
use App\Models\BirthdayMessageStep;
use App\Models\Setting;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminBirthdayMessageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            AdminPermissions::userCanAny($request->user(), [
                AdminPermissions::SETTINGS_VIEW,
                AdminPermissions::SETTINGS_EDIT,
                AdminPermissions::MANAGE_SETTINGS,
            ]),
            403,
        );

        $settings = BirthdayMessageSetting::current();
        if (BirthdayMessageStep::query()->where('kind', BirthdayMessageStep::KIND_WARM_WISH)->doesntExist()) {
            BirthdayMessageStep::query()->create([
                'days_before' => 0,
                'kind' => BirthdayMessageStep::KIND_WARM_WISH,
                'enabled' => true,
                'campaign_name' => '',
                'sort_order' => 40,
            ]);
        }
        if (BirthdayMessageStep::query()->where('days_before', 0)->where('kind', BirthdayMessageStep::KIND_MARKETING)->doesntExist()) {
            BirthdayMessageStep::query()->create([
                'days_before' => 0,
                'kind' => BirthdayMessageStep::KIND_MARKETING,
                'enabled' => true,
                'campaign_name' => '',
                'sort_order' => 30,
            ]);
        }

        $steps = BirthdayMessageStep::query()
            ->orderByDesc('days_before')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BirthdayMessageStep $step) => [
                'id' => $step->id,
                'days_before' => $step->days_before,
                'kind' => $step->kind,
                'enabled' => $step->enabled,
                'campaign_name' => $step->campaign_name ?? '',
                'image_path' => $step->image_path,
                'image_url' => $step->publicImageUrl(),
                'sort_order' => $step->sort_order,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/BirthdayMessages/Index', [
            'settings' => [
                'enabled' => $settings->enabled,
                'aisensy_account_id' => $settings->aisensy_account_id ?? '',
            ],
            'steps' => $steps,
            'can_edit' => AdminPermissions::userCanAny($request->user(), [
                AdminPermissions::SETTINGS_EDIT,
                AdminPermissions::MANAGE_SETTINGS,
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(
            AdminPermissions::userCanAny($request->user(), [
                AdminPermissions::SETTINGS_EDIT,
                AdminPermissions::MANAGE_SETTINGS,
            ]),
            403,
        );

        $validated = $request->validate([
            'settings.enabled' => ['required', 'boolean'],
            'settings.aisensy_account_id' => ['nullable', 'string', 'max:64'],
            'steps' => ['required', 'array'],
            'steps.*.id' => ['nullable', 'integer', 'exists:birthday_message_steps,id'],
            'steps.*.days_before' => ['required', 'integer', 'min:0', 'max:366'],
            'steps.*.kind' => ['required', Rule::in([BirthdayMessageStep::KIND_MARKETING, BirthdayMessageStep::KIND_WARM_WISH])],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.campaign_name' => ['nullable', 'string', 'max:255'],
            'steps.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'steps.*.remove_image' => ['nullable', 'boolean'],
            'steps.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        foreach ($validated['steps'] as $index => $row) {
            $enabled = filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $campaign = trim((string) ($row['campaign_name'] ?? ''));

            if ($enabled && $campaign === '') {
                throw ValidationException::withMessages([
                    "steps.{$index}.campaign_name" => 'AiSensy campaign name is required when a step is enabled.',
                ]);
            }
        }

        $settings = BirthdayMessageSetting::current();
        $settings->update([
            'enabled' => (bool) $validated['settings']['enabled'],
            'aisensy_account_id' => filled($validated['settings']['aisensy_account_id'] ?? null)
                ? trim((string) $validated['settings']['aisensy_account_id'])
                : null,
        ]);

        // Keep legacy settings toggles in sync with this panel (cron / older code paths).
        Setting::query()->updateOrCreate(
            ['key' => Setting::SEND_BIRTHDAY_WHATSAPP],
            [
                'value' => $settings->enabled ? '1' : '0',
                'label' => 'Send Birthday WhatsApp',
                'group' => 'notifications',
            ],
        );
        Setting::query()->updateOrCreate(
            ['key' => Setting::AISENSY_BIRTHDAY_ACCOUNT_ID],
            [
                'value' => $settings->aisensy_account_id ?? '',
                'label' => 'AiSensy Birthday Account Id',
                'group' => 'notifications',
            ],
        );

        $keptIds = [];

        foreach ($validated['steps'] as $index => $row) {
            if (($row['kind'] === BirthdayMessageStep::KIND_WARM_WISH) && (int) $row['days_before'] !== 0) {
                continue;
            }

            $step = ! empty($row['id'])
                ? BirthdayMessageStep::query()->find($row['id'])
                : new BirthdayMessageStep;

            if ($step === null) {
                continue;
            }

            $step->fill([
                'days_before' => (int) $row['days_before'],
                'kind' => $row['kind'],
                'enabled' => (bool) $row['enabled'],
                'campaign_name' => trim((string) ($row['campaign_name'] ?? '')),
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
            ]);

            $uploaded = $request->file("steps.{$index}.image");
            if ($uploaded) {
                if ($step->image_path) {
                    $this->deleteStoredPath($step->image_path);
                }
                $path = $uploaded->store('birthday-marketing', 'public');
                $step->image_path = 'storage/'.$path;
            } elseif (! empty($row['remove_image'])) {
                if ($step->image_path) {
                    $this->deleteStoredPath($step->image_path);
                }
                $step->image_path = null;
            }

            $step->save();
            $keptIds[] = $step->id;
        }

        if ($keptIds !== []) {
            BirthdayMessageStep::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            BirthdayMessageStep::query()->delete();
        }

        $warmWishCampaign = BirthdayMessageStep::query()
            ->where('kind', BirthdayMessageStep::KIND_WARM_WISH)
            ->where('days_before', 0)
            ->value('campaign_name');

        if (filled($warmWishCampaign)) {
            Setting::query()->updateOrCreate(
                ['key' => Setting::AISENSY_BIRTHDAY_CAMPAIGN],
                [
                    'value' => $warmWishCampaign,
                    'label' => 'AiSensy Birthday Campaign',
                    'group' => 'notifications',
                ],
            );
        }

        return redirect()
            ->route('admin.birthday-messages.index')
            ->with('status', 'Birthday message settings saved.');
    }

    private function deleteStoredPath(string $path): void
    {
        $relative = str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : ltrim($path, '/');

        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }
}
