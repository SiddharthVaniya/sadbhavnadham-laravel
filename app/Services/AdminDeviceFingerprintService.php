<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDeviceFingerprint;

class AdminDeviceFingerprintService
{
    /**
     * Allow login only when this fingerprint was added manually (e.g. phpMyAdmin).
     * Never auto-registers devices.
     *
     * @return array{ok: bool, matched: bool, message: ?string}
     */
    public function assertTrusted(User $user, string $fingerprint): array
    {
        $fingerprint = trim($fingerprint);

        if ($fingerprint === '') {
            return [
                'ok' => false,
                'matched' => false,
                'message' => 'Device unrecognized.',
            ];
        }

        $existing = UserDeviceFingerprint::query()
            ->where('user_id', $user->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if (! $existing) {
            return [
                'ok' => false,
                'matched' => false,
                'message' => 'Device unrecognized. Error id: '.$fingerprint,
            ];
        }

        $existing->forceFill(['last_used_at' => now()])->save();

        return [
            'ok' => true,
            'matched' => true,
            'message' => null,
        ];
    }
}
