<?php

namespace App\Http\Requests\Admin\Concerns;

trait NormalizesAdminDonorContact
{
    protected function normalizeAdminDonorContact(): void
    {
        if ($this->has('receipt_number') && $this->input('receipt_number') === '') {
            $this->merge(['receipt_number' => null]);
        }

        $pincode = trim((string) $this->input('pincode', ''));

        if ($pincode !== '') {
            $this->merge([
                'pincode' => strtoupper((string) preg_replace('/\s+/', ' ', $pincode)),
            ]);
        } elseif ($this->has('pincode')) {
            $this->merge(['pincode' => null]);
        }

        $dial = preg_replace('/\D+/', '', (string) $this->input('phone_dial_code', '91')) ?: '91';
        $national = preg_replace('/\D+/', '', (string) $this->input('donor_phone', ''));

        if ($national === '') {
            if ($this->has('donor_phone')) {
                $this->merge(['donor_phone' => null]);
            }

            return;
        }

        if (str_starts_with($national, $dial) && strlen($national) > strlen($dial) + 5) {
            $this->merge(['donor_phone' => $national]);

            return;
        }

        if ($dial === '91') {
            if (str_starts_with($national, '91') && strlen($national) === 12) {
                $national = substr($national, 2);
            }

            $this->merge(['donor_phone' => $national]);

            return;
        }

        $this->merge(['donor_phone' => $dial.$national]);
    }
}
