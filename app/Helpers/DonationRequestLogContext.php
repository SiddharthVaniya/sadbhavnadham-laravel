<?php

namespace App\Helpers;

class DonationRequestLogContext
{
    public static function fromInput(array $input): array
    {
        return [
            'cause' => $input['cause'] ?? null,
            'package_id' => $input['package_id'] ?? null,
            'amount' => $input['amount'] ?? null,
            'quantity' => $input['quantity'] ?? null,
            'country' => isset($input['country']) ? strtoupper((string) $input['country']) : null,
            'donor_country' => isset($input['donor_country']) ? strtoupper((string) $input['donor_country']) : null,
            'consent_indian_citizen' => (bool) ($input['consent_indian_citizen'] ?? false),
            'has_pan_number' => filled($input['pan_number'] ?? null),
        ];
    }
}
