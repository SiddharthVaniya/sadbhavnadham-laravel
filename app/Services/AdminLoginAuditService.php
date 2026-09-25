<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;

class AdminLoginAuditService
{
    public function __construct(
        private IpGeolocationService $geolocation,
    ) {}

    public function log(
        Request $request,
        string $status,
        ?User $user = null,
        ?string $email = null,
        ?string $fingerprint = null,
        ?bool $fingerprintMatched = null,
    ): UserLoginLog {
        $ip = $request->ip();
        $geo = $this->geolocation->lookup($ip);

        return UserLoginLog::query()->create([
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'fingerprint' => $fingerprint,
            'fingerprint_matched' => $fingerprintMatched,
            'status' => $status,
            'ip_address' => $ip,
            'country' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'location' => $geo['location'],
            'user_agent' => substr((string) $request->userAgent(), 0, 2000) ?: null,
            'created_at' => now(),
        ]);
    }
}
