<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Donation Receipt</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14px;
            padding: 30px;
            color: #333;
        }
        .header, .footer {
            text-align: center;
        }
        .logo {
            height: 70px;
            margin-bottom: 10px;
        }
        .trust-info {
            font-size: 12px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #00796b;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 15px;
        }
        .section p {
            margin: 4px 0;
        }
        .highlight {
            font-weight: bold;
            color: #000;
        }
        .amount-box {
            text-align: center;
            font-size: 20px;
            border: 2px solid #00796b;
            padding: 10px;
            margin: 20px 0;
            font-weight: bold;
        }
        .signature {
            margin-top: 40px;
            text-align: right;
        }
        .signature img {
            height: 40px;
        }
        .tax-note {
            font-size: 12px;
            margin-top: 10px;
            color: #555;
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="{{ \App\Support\Branding::assetUrl('logo') }}" class="logo" alt="{{ \App\Support\Branding::shortName() }} Logo">
        <div class="trust-info">
            <strong>સદભાવના વૃદ્ધાશ્રમ</strong><br>
            Reg. No: E-9897 RAJKOT | PAN: AADTM7770L | URN: AADTM7770LF20216<br>
            Rajkot, Gujarat – 380001
        </div>
        <div class="title">Donation Receipt</div>
    </div>

    <div class="section">
        <p><span class="highlight">Receipt No:</span> {{ strtoupper(substr($data['payload']['payment']['entity']['id'], -6)) }}</p>
        <p><span class="highlight">Date:</span> {{ \Carbon\Carbon::createFromTimestamp($data['payload']['payment']['entity']['created_at'])->format('d/m/Y') }}</p>
    </div>

    <div class="section">
        <p><span class="highlight">Donor Name:</span> {{ $data['payload']['payment']['entity']['email'] }}</p>
        <p><span class="highlight">Contact:</span> {{ $data['payload']['payment']['entity']['contact'] }}</p>
        <p><span class="highlight">PAN Number:</span> {{ $data['payload']['payment']['entity']['notes']['pan_number'] ?? 'N/A' }}</p>
        <p><span class="highlight">Donation For:</span> {{ $data['payload']['payment']['entity']['notes']['donate_for'] }}</p>
        <p><span class="highlight">Amount (in words):</span>
            {{ ucwords((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($data['payload']['payment']['entity']['amount'] / 100)) }} Rupees only
        </p>
    </div>

    <div class="amount-box">
        ₹{{ \App\Helpers\NumberHelper::formatWholeAmount($data['payload']['payment']['entity']['amount'] / 100) }}
    </div>

    <div class="tax-note">
        This donation is eligible for tax exemption under Section 80G of the Income Tax Act, 1961.
    </div>

    <div class="signature">
        <p>Authorized Signatory</p>
        <img src="https://sienna-guanaco-478048.hostingersite.com/wp-content/uploads/2025/05/sign-v-turst-scaled.png" alt="Signature">
    </div>

    <div class="footer">
        {{ \App\Support\Branding::receiptThankYouText() }}
    </div>

</body>
</html>
