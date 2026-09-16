<?php

namespace App\Services;

use App\Models\Donor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DonorCsvImportService
{
    public const MAX_ROWS = 2000;

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'name',
            'email',
            'phone',
            'date_of_birth',
            'pan_number',
            'address',
            'pincode',
            'city',
            'state',
            'country',
            'country_code',
            'whatsapp_opt_out',
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Unable to read the uploaded CSV file.'],
            ];
        }

        $headerRow = fgetcsv($handle) ?: [];
        $headerRow = array_map(fn ($value) => $this->normalizeHeader((string) $value), $headerRow);

        if ($headerRow !== [] && str_starts_with((string) ($headerRow[0] ?? ''), "\u{FEFF}")) {
            $headerRow[0] = $this->normalizeHeader(ltrim((string) $headerRow[0], "\u{FEFF}"));
        }

        $map = $this->mapHeaders($headerRow);

        if (! isset($map['name'], $map['email'], $map['phone'])) {
            fclose($handle);

            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['CSV must include name, email, and phone columns.'],
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        DB::transaction(function () use ($handle, $map, &$created, &$updated, &$skipped, &$errors, &$rowNumber): void {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber - 1 > self::MAX_ROWS) {
                    $errors[] = 'Stopped after '.self::MAX_ROWS.' data rows. Split the file and import again.';
                    break;
                }

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $payload = $this->rowToPayload($row, $map);

                if ($payload['name'] === '' || $payload['email'] === '' || $payload['phone'] === '') {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: name, email, and phone are required.";

                    continue;
                }

                if (! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $errors[] = "Row {$rowNumber}: invalid email “{$payload['email']}”.";

                    continue;
                }

                $donor = Donor::query()->firstOrNew([
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                ]);

                $wasExisting = $donor->exists;
                $donor->fill($payload);
                $donor->save();

                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }
            }
        });

        fclose($handle);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 25),
        ];
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if (in_array($normalized, self::headers(), true)) {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @return array<string, mixed>
     */
    private function rowToPayload(array $row, array $map): array
    {
        $get = function (string $key) use ($row, $map): string {
            if (! isset($map[$key])) {
                return '';
            }

            return trim((string) ($row[$map[$key]] ?? ''));
        };

        $email = mb_strtolower($get('email'));
        $phone = preg_replace('/\s+/', '', $get('phone')) ?? '';
        $dobRaw = $get('date_of_birth');
        $dob = null;

        if ($dobRaw !== '') {
            try {
                $dob = Carbon::parse($dobRaw)->toDateString();
            } catch (\Throwable) {
                $dob = null;
            }
        }

        $optOutRaw = strtolower($get('whatsapp_opt_out'));
        $optOut = in_array($optOutRaw, ['1', 'true', 'yes', 'y'], true);

        return [
            'name' => $get('name'),
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dob,
            'pan_number' => strtoupper($get('pan_number')) ?: null,
            'address' => $get('address') ?: null,
            'pincode' => $get('pincode') ?: null,
            'city' => $get('city') ?: null,
            'state' => $get('state') ?: null,
            'country' => $get('country') !== '' ? $get('country') : 'INDIA',
            'country_code' => $get('country_code') !== '' ? strtoupper($get('country_code')) : 'IN',
            'whatsapp_opt_out' => $optOut,
            'whatsapp_opted_out_at' => $optOut ? now() : null,
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return $header;
    }
}
