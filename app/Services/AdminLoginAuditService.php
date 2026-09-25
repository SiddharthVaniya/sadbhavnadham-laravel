<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\GeoIp\GeoIpLookupService;
use Illuminate\Http\Request;

class AdminLoginAuditService
{
    public function __construct(
        private GeoIpLookupService $geoIp,
    ) {}

    public function log(
        Request $request,
        string $status,
        ?User $user = null,
        ?string $email = null,
        ?string $fingerprint = null,
        ?bool $fingerprintMatched = null,
    ): UserLoginLog {
        $result = $this->geoIp->lookupFromRequest($request);
        $geo = $result->toLoginLogArray();
        $ip = $result->ipAddress
            ?? $this->geoIp->clientIp($request, publicOnly: false)
            ?? $request->ip();

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
            'postal_code' => $geo['postal_code'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'timezone' => $geo['timezone'] ?? null,
            'asn' => $geo['asn'] ?? null,
            'isp' => $geo['isp'] ?? null,
            'user_agent' => substr((string) $request->userAgent(), 0, 2000) ?: null,
            'created_at' => now(),
        ]);
    }
}
