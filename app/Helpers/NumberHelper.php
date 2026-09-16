<?php

namespace App\Helpers;

class NumberHelper
{
    public static function amountInWords(float $amount): string
    {
        if (! class_exists(\NumberFormatter::class)) {
            return 'Rupees '.self::formatWholeAmount($amount);
        }

        $formatter = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);

        return ucfirst((string) $formatter->format($amount)).' Rupees';
    }

    public static function formatWholeAmount(float|int|string $amount): string
    {
        return (string) (int) round((float) $amount);
    }

    public static function formatInr(float|int|string $amount): string
    {
        return '₹'.number_format((float) $amount, 2, '.', ',');
    }

    /**
     * PDF-safe INR formatting (DomPDF fonts often lack the ₹ glyph).
     */
    public static function formatInrForPdf(float|int|string $amount): string
    {
        return 'Rs. '.number_format((float) $amount, 2, '.', ',');
    }
}
