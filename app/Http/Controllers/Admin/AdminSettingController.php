<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingValueRequest;
use App\Models\Setting;
use App\Support\AdminInertiaResources;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::query()
            ->where('key', '!=', Setting::BRANDING_OVERRIDES)
            ->orderBy('group')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings->map(fn (Setting $setting) => AdminInertiaResources::setting($setting)),
        ]);
    }

    public function toggle(Setting $setting): JsonResponse
    {
        abort_if($setting->key === Setting::BRANDING_OVERRIDES, 404);
        abort_unless(Setting::isToggleKey($setting->key), 404);

        $setting->update(['value' => $setting->value ? '0' : '1']);

        return response()->json([
            'enabled' => (bool) $setting->value,
            'label' => $setting->label,
        ]);
    }

    public function update(UpdateSettingValueRequest $request, Setting $setting): JsonResponse
    {
        abort_if($setting->key === Setting::BRANDING_OVERRIDES, 404);

        $value = $request->validated('value');

        if (Setting::isIntegerKey($setting->key)) {
            $value = (string) (int) $value;
        } else {
            $value = trim((string) ($value ?? ''));
        }

        $setting->update(['value' => $value]);

        return response()->json([
            'value' => $setting->value,
            'label' => $setting->label,
        ]);
    }
}
