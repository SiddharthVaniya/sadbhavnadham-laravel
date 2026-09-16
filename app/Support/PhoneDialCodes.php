<?php

namespace App\Support;

class PhoneDialCodes
{
    /**
     * @return list<array{iso: string, dial: string, label: string, country: string}>
     */
    public static function all(): array
    {
        return [
            ['iso' => 'IN', 'dial' => '91', 'label' => 'India (+91)', 'country' => 'INDIA'],
            ['iso' => 'GB', 'dial' => '44', 'label' => 'United Kingdom (+44)', 'country' => 'UNITED KINGDOM'],
            ['iso' => 'US', 'dial' => '1', 'label' => 'United States (+1)', 'country' => 'UNITED STATES'],
            ['iso' => 'CA', 'dial' => '1', 'label' => 'Canada (+1)', 'country' => 'CANADA'],
            ['iso' => 'AE', 'dial' => '971', 'label' => 'UAE (+971)', 'country' => 'UNITED ARAB EMIRATES'],
            ['iso' => 'AU', 'dial' => '61', 'label' => 'Australia (+61)', 'country' => 'AUSTRALIA'],
            ['iso' => 'SG', 'dial' => '65', 'label' => 'Singapore (+65)', 'country' => 'SINGAPORE'],
            ['iso' => 'NZ', 'dial' => '64', 'label' => 'New Zealand (+64)', 'country' => 'NEW ZEALAND'],
            ['iso' => 'DE', 'dial' => '49', 'label' => 'Germany (+49)', 'country' => 'GERMANY'],
            ['iso' => 'FR', 'dial' => '33', 'label' => 'France (+33)', 'country' => 'FRANCE'],
            ['iso' => 'NL', 'dial' => '31', 'label' => 'Netherlands (+31)', 'country' => 'NETHERLANDS'],
            ['iso' => 'IE', 'dial' => '353', 'label' => 'Ireland (+353)', 'country' => 'IRELAND'],
            ['iso' => 'ZA', 'dial' => '27', 'label' => 'South Africa (+27)', 'country' => 'SOUTH AFRICA'],
            ['iso' => 'KE', 'dial' => '254', 'label' => 'Kenya (+254)', 'country' => 'KENYA'],
            ['iso' => 'NG', 'dial' => '234', 'label' => 'Nigeria (+234)', 'country' => 'NIGERIA'],
            ['iso' => 'PK', 'dial' => '92', 'label' => 'Pakistan (+92)', 'country' => 'PAKISTAN'],
            ['iso' => 'BD', 'dial' => '880', 'label' => 'Bangladesh (+880)', 'country' => 'BANGLADESH'],
            ['iso' => 'NP', 'dial' => '977', 'label' => 'Nepal (+977)', 'country' => 'NEPAL'],
            ['iso' => 'LK', 'dial' => '94', 'label' => 'Sri Lanka (+94)', 'country' => 'SRI LANKA'],
            ['iso' => 'MY', 'dial' => '60', 'label' => 'Malaysia (+60)', 'country' => 'MALAYSIA'],
            ['iso' => 'TH', 'dial' => '66', 'label' => 'Thailand (+66)', 'country' => 'THAILAND'],
            ['iso' => 'QA', 'dial' => '974', 'label' => 'Qatar (+974)', 'country' => 'QATAR'],
            ['iso' => 'SA', 'dial' => '966', 'label' => 'Saudi Arabia (+966)', 'country' => 'SAUDI ARABIA'],
            ['iso' => 'KW', 'dial' => '965', 'label' => 'Kuwait (+965)', 'country' => 'KUWAIT'],
            ['iso' => 'OM', 'dial' => '968', 'label' => 'Oman (+968)', 'country' => 'OMAN'],
            ['iso' => 'BH', 'dial' => '973', 'label' => 'Bahrain (+973)', 'country' => 'BAHRAIN'],
            ['iso' => 'HK', 'dial' => '852', 'label' => 'Hong Kong (+852)', 'country' => 'HONG KONG'],
            ['iso' => 'JP', 'dial' => '81', 'label' => 'Japan (+81)', 'country' => 'JAPAN'],
            ['iso' => 'CN', 'dial' => '86', 'label' => 'China (+86)', 'country' => 'CHINA'],
        ];
    }

    /**
     * @return array{iso: string, dial: string, label: string, country: string}
     */
    public static function find(string $isoOrDial): array
    {
        $value = strtoupper(trim($isoOrDial));

        foreach (self::all() as $row) {
            if ($row['iso'] === $value || $row['dial'] === $value || $row['dial'] === ltrim($value, '+')) {
                return $row;
            }
        }

        return self::all()[0];
    }

    /**
     * @return list<string>
     */
    public static function dialValues(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): string => $row['dial'],
            self::all(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function isoValues(): array
    {
        return array_map(static fn (array $row): string => $row['iso'], self::all());
    }

    /**
     * Build stored-phone lookup candidates from dial + national number.
     *
     * @return list<string>
     */
    public static function lookupCandidates(string $national, string $dial = '91'): array
    {
        $dial = preg_replace('/\D+/', '', $dial) ?: '91';
        $national = preg_replace('/\D+/', '', $national) ?? '';

        if ($national === '') {
            return [];
        }

        if (str_starts_with($national, $dial) && strlen($national) > strlen($dial) + 5) {
            return array_values(array_unique([$national, substr($national, strlen($dial))]));
        }

        if ($dial === '91') {
            if (str_starts_with($national, '91') && strlen($national) === 12) {
                $national = substr($national, 2);
            }

            return array_values(array_unique([$national, '91'.$national]));
        }

        return array_values(array_unique([$dial.$national, $national]));
    }

    /**
     * Canonical storage format matching admin offline donations.
     */
    public static function storePhone(string $national, string $dial = '91'): string
    {
        $candidates = self::lookupCandidates($national, $dial);

        if ($candidates === []) {
            return '';
        }

        if ($dial === '91' || $dial === '') {
            return $candidates[0];
        }

        return $candidates[0];
    }
}
