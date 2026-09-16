<?php

namespace App\Support;

class DonationReceiptStyles
{
    public const TINT_BG = '#dce8f3';

    public const NAVY = '#0D3B5E';

    public const BORDER = '#c5d4e3';

    public const TEXT = '#1f2933';

    public const MUTED = '#5b6570';

    public const MUTED_TEXT = '#666666';

    public const ROW_STRIPE = '#f4f8fb';

    public const PACKAGE = '#6b6b6b';

    /**
     * @return array<string, string>
     */
    public static function inline(bool $isEmail = false, bool $isPdf = false): array
    {
        $font = self::fontFamily($isEmail, $isPdf);

        return [
            'body' => 'margin:0;padding:0;background:#ffffff;color:'.self::TEXT.";font-family:{$font};font-size:14px;line-height:1.5;",
            'receipt' => $isPdf
                ? 'max-width:100%;margin:0;padding:0;'
                : 'max-width:760px;margin:0 auto;padding:20px 16px 24px;',
            'container' => 'padding:28px 32px;background:#ffffff;font-family:'.$font.';',
            'logo' => 'max-height:64px;max-width:180px;',
            'title_box' => 'background:'.self::TINT_BG.';color:'.self::NAVY.';font-size:18px;font-weight:700;letter-spacing:0.12em;text-align:center;padding:14px 18px;font-family:'.$font.';',
            'meta_label' => 'color:'.self::MUTED.';font-weight:400;width:150px;padding:3px 0;font-size:13px;font-family:'.$font.';',
            'meta_value' => 'font-weight:600;color:'.self::TEXT.';padding:3px 0;font-size:13px;font-family:'.$font.';',
            'section_bar' => 'background:'.self::TINT_BG.';color:'.self::NAVY.';font-size:12px;font-weight:600;letter-spacing:0.14em;padding:7px 10px;margin:22px 0 12px;font-family:'.$font.';',
            'party_name' => 'font-weight:600;font-size:15px;line-height:1.5;font-family:'.$font.';color:'.self::TEXT.';',
            'party_detail' => 'color:#444444;padding-top:3px;line-height:1.5;font-family:'.$font.';font-size:14px;',
            'party_detail_first' => 'color:#444444;padding-top:0;line-height:1.5;font-family:'.$font.';font-size:14px;',
            'items_table' => 'width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed;font-family:'.$font.';',
            'items_th' => 'background:'.self::TINT_BG.';color:'.self::NAVY.';font-size:11px;font-weight:600;letter-spacing:0.08em;text-align:left;padding:8px 10px;border:1px solid '.self::BORDER.';font-family:'.$font.';',
            'items_th_right' => 'background:'.self::TINT_BG.';color:'.self::NAVY.';font-size:11px;font-weight:600;letter-spacing:0.08em;text-align:right;padding:8px 10px;border:1px solid '.self::BORDER.';font-family:'.$font.';',
            'items_td' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:#ffffff;font-family:'.$font.';font-size:14px;word-wrap:break-word;',
            'items_td_right' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:#ffffff;font-family:'.$font.';font-size:14px;text-align:right;white-space:nowrap;',
            'items_td_qty' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:#ffffff;font-family:'.$font.';font-size:14px;text-align:right;',
            'items_td_stripe' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:'.self::ROW_STRIPE.';font-family:'.$font.';font-size:14px;word-wrap:break-word;',
            'items_td_stripe_right' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:'.self::ROW_STRIPE.';font-family:'.$font.';font-size:14px;text-align:right;white-space:nowrap;',
            'items_td_stripe_qty' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:'.self::ROW_STRIPE.';font-family:'.$font.';font-size:14px;text-align:right;',
            'notes_cell' => 'border:1px solid '.self::BORDER.';color:'.self::MUTED_TEXT.';font-size:12px;padding:10px 12px 10px 10px;vertical-align:top;font-family:'.$font.';',
            'totals_table' => 'width:100%;border-collapse:collapse;table-layout:fixed;margin-top:-1px;font-family:'.$font.';',
            'totals_label' => 'border:1px solid '.self::BORDER.';text-align:right;padding:4px 10px;font-size:13px;background:#ffffff;font-family:'.$font.';color:'.self::TEXT.';',
            'totals_value' => 'border:1px solid '.self::BORDER.';text-align:right;padding:4px 10px;font-size:13px;background:#ffffff;font-family:'.$font.';color:'.self::TEXT.';white-space:nowrap;',
            'grand_label' => 'border:1px solid '.self::BORDER.';text-align:right;padding:8px 10px;font-size:15px;background:'.self::TINT_BG.';color:'.self::NAVY.';font-weight:600;font-family:'.$font.';',
            'grand_value' => 'border:1px solid '.self::BORDER.';text-align:right;padding:8px 10px;font-size:15px;background:'.self::TINT_BG.';color:'.self::NAVY.';font-weight:600;font-family:'.$font.';white-space:nowrap;',
            'cause' => 'font-weight:600;font-family:'.$font.';color:'.self::TEXT.';',
            'package' => 'color:'.self::PACKAGE.';font-size:12px;font-weight:400;padding-top:3px;font-family:'.$font.';',
            'sign_label' => 'color:'.self::NAVY.';font-size:12px;font-weight:600;letter-spacing:0.04em;padding-top:6px;font-family:'.$font.';',
            'signature_block' => 'page-break-inside:avoid;margin-top:36px;',
            'statutory_table' => 'width:100%;border-collapse:collapse;table-layout:fixed;margin-top:32px;border-top:1px solid '.self::NAVY.';padding-top:14px;font-family:'.$font.';page-break-inside:avoid;',
            'statutory_label' => 'color:'.self::MUTED.';font-size:11px;font-weight:400;letter-spacing:0.04em;padding-top:8px;font-family:'.$font.';',
            'statutory_value' => 'color:'.self::TEXT.';font-size:13px;font-weight:600;padding-top:3px;font-family:'.$font.';',
        ];
    }

    /**
     * Full style map for receipt views, with safe defaults for keys added over time.
     *
     * @return array<string, string>
     */
    public static function forView(bool $isEmail = false, bool $isPdf = false): array
    {
        $font = self::fontFamily($isEmail, $isPdf);
        $inline = self::inline($isEmail, $isPdf);

        $defaults = [
            'container' => 'padding:28px 32px;background:#ffffff;font-family:'.$font.';',
            'items_td_qty' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:#ffffff;font-family:'.$font.';font-size:14px;text-align:right;',
            'items_td_stripe_qty' => 'padding:10px;border:1px solid '.self::BORDER.';vertical-align:top;color:'.self::TEXT.';background:'.self::ROW_STRIPE.';font-family:'.$font.';font-size:14px;text-align:right;',
            'totals_table' => 'width:100%;border-collapse:collapse;table-layout:fixed;margin-top:-1px;font-family:'.$font.';',
            'signature_block' => 'page-break-inside:avoid;margin-top:36px;',
        ];

        return array_replace($defaults, $inline);
    }

    public static function fontFamily(bool $isEmail = false, bool $isPdf = false): string
    {
        if ($isEmail) {
            return 'Arial, Helvetica, sans-serif';
        }

        if ($isPdf) {
            return 'DejaVu Sans, Helvetica, Arial, sans-serif';
        }

        return "'Poppins', 'DejaVu Sans', Helvetica, Arial, sans-serif";
    }
}
