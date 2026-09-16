<?php

namespace App\Support;

use App\Models\Donor;

class DonorPortalSession
{
    public const SESSION_KEY = 'donor_portal';

    public function __construct(private DonorOtpService $otpService) {}

    public function login(Donor $donor): void
    {
        session([
            self::SESSION_KEY => [
                'donor_id' => $donor->id,
                'verified_at' => now()->timestamp,
                'profile' => $this->otpService->profilePayload($donor),
            ],
        ]);
    }

    public function logout(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function donorId(): ?int
    {
        $donorId = data_get(session(self::SESSION_KEY), 'donor_id');

        return is_numeric($donorId) ? (int) $donorId : null;
    }

    public function donor(): ?Donor
    {
        $donorId = $this->donorId();

        if ($donorId === null) {
            return null;
        }

        return Donor::query()->find($donorId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(): ?array
    {
        $donor = $this->donor();

        if ($donor === null) {
            $this->logout();

            return null;
        }

        $profile = $this->otpService->profilePayload($donor);

        session([
            self::SESSION_KEY => [
                'donor_id' => $donor->id,
                'verified_at' => data_get(session(self::SESSION_KEY), 'verified_at', now()->timestamp),
                'profile' => $profile,
            ],
        ]);

        return $profile;
    }

    /**
     * @return array{signed_in: bool, display_name: string|null, profile: array<string, mixed>|null}
     */
    public function toFrontend(): array
    {
        $profile = $this->profile();

        if ($profile === null) {
            return [
                'signed_in' => false,
                'display_name' => null,
                'profile' => null,
            ];
        }

        $name = trim((string) ($profile['donor_name'] ?? ''));
        $displayName = $name !== '' ? preg_split('/\s+/u', $name)[0] : 'Donor';

        return [
            'signed_in' => true,
            'display_name' => $displayName,
            'profile' => $profile,
        ];
    }
}
