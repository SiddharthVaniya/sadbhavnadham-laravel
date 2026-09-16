@php
    if ($order->exists) {
        $order->loadMissing(['items.causeModel', 'items.package', 'donor']);
    }
    $receiptImages = $receiptImages ?? \App\Support\ReceiptAssets::all();
    $logoSrc = $pdfImages['logo'] ?? $receiptImages['logo'] ?? \App\Support\Branding::assetUrl('logo_public');
    $signatureSrc = (isset($pdfSignatureData) && $pdfSignatureData !== '')
        ? $pdfSignatureData
        : (isset($pdfSignaturePath)
            ? 'file:///'.str_replace('\\', '/', $pdfSignaturePath)
            : asset('images/signature.jpeg'));
    $paidAt = \Carbon\Carbon::parse($order->paid_at ?? $order->created_at ?? now())
        ->timezone(config('app.timezone', 'Asia/Kolkata'));
    $transactionReference = $order->hasReceipt()
        ? $order->receiptNumberFormatted()
        : (string) ($order->provider_payment_id ?: $order->order_uuid ?: 'ORD-'.$order->id);
    $phone = preg_replace('/\D+/', '', (string) $order->donor_phone) ?? '';
    $phoneDisplay = strlen($phone) === 10 ? '+91 '.$phone : (string) $order->donor_phone;
    $donorAddressParts = array_filter([
        $order->address ?: $order->donor?->address,
        $order->city ?: $order->donor?->city,
        $order->state ?: $order->donor?->state,
        $order->pincode ?: $order->donor?->pincode,
        $order->country ?: $order->donor?->country,
    ], static fn ($value) => $value !== null && trim((string) $value) !== '');
    $lineItems = \App\Support\DonationReceiptLineItems::forOrder($order);
    $brand = \App\Support\Branding::shortName();
    $paymentMethod = \App\Models\DonationOrder::providerDisplayName($order->payment_provider);
    $pdfPoppinsFonts = $pdfPoppinsFonts ?? [];
    $poppinsRegular = $pdfPoppinsFonts['regular'] ?? asset('fonts/Poppins-Regular.ttf');
    $poppinsSemibold = $pdfPoppinsFonts['semibold'] ?? asset('fonts/Poppins-SemiBold.ttf');
    $poppinsBold = $pdfPoppinsFonts['bold'] ?? asset('fonts/Poppins-Bold.ttf');
    $formatMoney = ($isPdf ?? false)
        ? static fn (float|int|string $amount): string => \App\Helpers\NumberHelper::formatInrForPdf($amount)
        : static fn (float|int|string $amount): string => \App\Helpers\NumberHelper::formatInr($amount);
    $isEmail = (bool) ($isEmail ?? false);
    $isPdf = (bool) ($isPdf ?? false);
    $s = \App\Support\DonationReceiptStyles::forView($isEmail, $isPdf);
    $fontFamily = \App\Support\DonationReceiptStyles::fontFamily($isEmail, $isPdf);
    $tintBg = \App\Support\DonationReceiptStyles::TINT_BG;
    $navy = \App\Support\DonationReceiptStyles::NAVY;
    $border = \App\Support\DonationReceiptStyles::BORDER;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Receipt</title>
    <style>
        @if (! $isEmail && ! $isPdf)
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $poppinsRegular }}') format('truetype');
        }
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 600;
            src: url('{{ $poppinsSemibold }}') format('truetype');
        }
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $poppinsBold }}') format('truetype');
        }
        @endif
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, table, td, th, div, p {
            font-family: {{ $fontFamily }};
        }
        body {
            background: #ffffff;
            color: #1f2933;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
        }
        .title-box {
            background: {{ $tintBg }};
            color: {{ $navy }};
        }
        .section-bar {
            background: {{ $tintBg }};
            color: {{ $navy }};
        }
        .items th {
            background: {{ $tintBg }};
            color: {{ $navy }};
            border: 1px solid {{ $border }};
        }
        .items td {
            border: 1px solid {{ $border }};
        }
        .items tfoot tr.grand td {
            background: {{ $tintBg }};
            color: {{ $navy }};
        }
        @if ($isPdf)
        @page {
            margin: 44px 28px 28px 28px;
        }
        @page :first {
            margin-top: 32px;
        }
        .items thead {
            display: table-header-group;
        }
        .items tbody tr {
            page-break-inside: avoid;
        }
        .receipt-signature,
        .receipt-statutory {
            page-break-inside: avoid;
        }
        @endif
        @media print {
            .receipt { padding: 0; max-width: none; }
        }
    </style>
</head>
<body style="{{ $s['body'] }}">
    <div class="receipt" style="{{ $s['receipt'] }}">
        <div class="receipt-container" style="{{ $s['container'] }}">
        @if (! empty($demoReceipt))
            <p class="demo-banner">Demo receipt — sample data, not a real donation.</p>
        @endif

        <table width="100%" style="border-collapse: collapse; margin-bottom: 18px;">
            <tr>
                <td style="vertical-align: middle; width: 58%;">
                    @if ($logoSrc)
                        <img src="{{ $logoSrc }}" alt="{{ $brand }}" class="logo" style="{{ $s['logo'] }}">
                    @endif
                </td>
                <td style="vertical-align: middle; width: 42%;">
                    <div class="title-box" style="{{ $s['title_box'] }}">DONATION RECEIPT</div>
                </td>
            </tr>
        </table>

        <table class="meta-row" style="border-collapse: collapse;">
            <tr>
                <td class="meta-label" style="{{ $s['meta_label'] }}">Receipt Number:</td>
                <td class="meta-value" style="{{ $s['meta_value'] }}">{{ $transactionReference }}</td>
            </tr>
            <tr>
                <td class="meta-label" style="{{ $s['meta_label'] }}">Receipt Date:</td>
                <td class="meta-value" style="{{ $s['meta_value'] }}">{{ $paidAt->format('j M Y, h:i A') }}</td>
            </tr>
            <tr>
                <td class="meta-label" style="{{ $s['meta_label'] }}">Payment Method:</td>
                <td class="meta-value" style="{{ $s['meta_value'] }}">{{ $paymentMethod }}</td>
            </tr>
        </table>

        <div class="section-bar" style="{{ $s['section_bar'] }}">DONOR</div>
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td valign="top" style="width: 48%; vertical-align: top;">
                    <div class="party-name" style="{{ $s['party_name'] }}">{{ $order->donor_name }}</div>
                    @if (filled($order->donor_email))
                        <div class="party-detail" style="{{ $s['party_detail'] }}">{{ $order->donor_email }}</div>
                    @endif
                    @if ($phoneDisplay !== '')
                        <div class="party-detail" style="{{ $s['party_detail'] }}">{{ $phoneDisplay }}</div>
                    @endif
                </td>
                <td valign="top" style="width: 52%; vertical-align: top;">
                    @forelse ($donorAddressParts as $index => $part)
                        <div class="party-detail" style="{{ $index === 0 ? $s['party_detail_first'] : $s['party_detail'] }}">{{ $part }}</div>
                    @empty
                        <div class="party-detail" style="{{ $s['party_detail_first'] }}">&nbsp;</div>
                    @endforelse
                </td>
            </tr>
        </table>

        <table class="items" width="100%" style="{{ $s['items_table'] }}">
            <colgroup>
                <col style="width:48%;">
                <col style="width:12%;">
                <col style="width:20%;">
                <col style="width:20%;">
            </colgroup>
            <thead>
                <tr>
                    <th style="{{ $s['items_th'] }}">DESCRIPTION</th>
                    <th style="{{ $s['items_th_right'] }}">QUANTITY</th>
                    <th style="{{ $s['items_th_right'] }}">UNIT PRICE</th>
                    <th style="{{ $s['items_th_right'] }}">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lineItems as $index => $line)
                    @php
                        $rowTd = $index % 2 === 1 ? $s['items_td_stripe'] : $s['items_td'];
                        $rowTdQty = $index % 2 === 1
                            ? ($s['items_td_stripe_qty'] ?? $s['items_td_stripe_right'])
                            : ($s['items_td_qty'] ?? $s['items_td_right']);
                        $rowTdRight = $index % 2 === 1 ? $s['items_td_stripe_right'] : $s['items_td_right'];
                    @endphp
                    <tr>
                        <td style="{{ $rowTd }}">
                            <div class="cause" style="{{ $s['cause'] }}">{{ $line->cause_title }}</div>
                            @if (filled($line->package_title))
                                <div class="package" style="{{ $s['package'] }}">Package: {{ $line->package_title }}</div>
                            @endif
                            @foreach ($line->honoree_names ?? [] as $honoree)
                                <div class="package" style="{{ $s['package'] }}">{{ $honoree }}</div>
                            @endforeach
                        </td>
                        <td style="{{ $rowTdQty }}">{{ $line->quantity }}</td>
                        <td style="{{ $rowTdRight }}">{{ $formatMoney($line->unit_amount) }}</td>
                        <td style="{{ $rowTdRight }}">{{ $formatMoney($line->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="items totals-table" width="100%" style="{{ $s['totals_table'] }}">
            <colgroup>
                <col style="width:48%;">
                <col style="width:12%;">
                <col style="width:20%;">
                <col style="width:20%;">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="2" rowspan="2" style="{{ $s['notes_cell'] }}">
                        Eligible for tax exemption under Section 80G of the Income Tax Act, 1961.
                    </td>
                    <td style="{{ $s['totals_label'] }}">Subtotal</td>
                    <td style="{{ $s['totals_value'] }}">{{ $formatMoney($order->total_amount) }}</td>
                </tr>
                <tr>
                    <td style="{{ $s['grand_label'] }}">TOTAL</td>
                    <td style="{{ $s['grand_value'] }}">{{ $formatMoney($order->total_amount) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="receipt-signature" style="{{ $s['signature_block'] }}">
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: bottom;">
                        @if ($signatureSrc)
                            <img src="{{ $signatureSrc }}" alt="" style="max-height: 48px; max-width: 160px;">
                        @else
                            <div style="border-bottom: 2px solid {{ $navy }}; height: 48px;"></div>
                        @endif
                        <div class="sign-label" style="{{ $s['sign_label'] }}">Authorized Signatory</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="statutory receipt-statutory" width="100%" style="{{ $s['statutory_table'] }}">
            <tr>
                <td valign="top" style="width:33.33%;vertical-align:top;">
                    <div class="statutory-label" style="{{ $s['statutory_label'] }}">PAN</div>
                    <div class="statutory-value" style="{{ $s['statutory_value'] }}">AADTM7770L</div>
                </td>
                <td valign="top" style="width:33.33%;vertical-align:top;text-align:center;">
                    <div class="statutory-label" style="{{ $s['statutory_label'] }}">Reg. No.</div>
                    <div class="statutory-value" style="{{ $s['statutory_value'] }}">E-9897 Rajkot</div>
                </td>
                <td valign="top" style="width:33.33%;vertical-align:top;text-align:right;">
                    <div class="statutory-label" style="{{ $s['statutory_label'] }}">URN</div>
                    <div class="statutory-value" style="{{ $s['statutory_value'] }}">AADTM7770LF20216</div>
                </td>
            </tr>
        </table>
        </div>
    </div>
</body>
</html>
