@extends('layouts.donate')

@section('title', 'Bank Details | ' . $branding['name'])
@section('meta_description', 'Donate via NEFT/IMPS/UPI using official bank account details for ' . $branding['legalName'] . '.')
@section('canonical', \App\Support\Seo::canonicalUrl(route('donate.bank-details', [], false)))

@section('content')
    @php
        $bank = $branding['bank'] ?? [];
        $contact = $branding['contact'] ?? [];
        $rows = array_values(array_filter([
            ['label' => 'Account Name', 'value' => $bank['account_name'] ?? '', 'copy' => true],
            ['label' => 'Account Number', 'value' => $bank['account_number'] ?? '', 'copy' => true],
            ['label' => 'IFSC', 'value' => $bank['ifsc'] ?? '', 'copy' => true],
            ['label' => 'Bank Name', 'value' => $bank['bank_name'] ?? '', 'copy' => true],
            ['label' => 'Branch', 'value' => $bank['branch'] ?? '', 'copy' => true],
            ['label' => 'Account Type', 'value' => $bank['account_type'] ?? '', 'copy' => false],
            ['label' => 'UPI ID', 'value' => $bank['upi_id'] ?? '', 'copy' => true],
        ], fn ($row) => filled($row['value'])));
    @endphp

    <section class="bank-page">
        <div class="bank-page__intro">
            <p class="bank-page__eyebrow">NEFT · IMPS · UPI</p>
            <h1 class="bank-page__title">Bank Details</h1>
            <p class="bank-page__lead">
                Transfer directly to our official account. Use Copy on any field, or copy everything at once for your banking app.
            </p>
        </div>

        <div class="bank-page__layout">
            <div class="bank-page__panel">
                <div class="bank-page__panel-head">
                    <div>
                        <p class="bank-page__label">Official account</p>
                        <h2 class="bank-page__legal">{{ $branding['legalName'] }}</h2>
                    </div>
                    <button type="button" class="bank-page__copy-all" data-copy-all @disabled($rows === [])>
                        Copy all details
                    </button>
                </div>

                @if ($rows === [])
                    <p class="bank-page__empty">Bank details will appear here soon. Please donate online or contact us.</p>
                @else
                    <ul class="bank-page__list">
                        @foreach ($rows as $row)
                            <li class="bank-page__row">
                                <div class="bank-page__meta">
                                    <span class="bank-page__label">{{ $row['label'] }}</span>
                                    <strong class="bank-page__value" data-copy-value>{{ $row['value'] }}</strong>
                                </div>
                                @if ($row['copy'])
                                    <button type="button" class="bank-page__copy" data-copy="{{ $row['value'] }}">Copy</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($bank['note']))
                    <p class="bank-page__note">{{ $bank['note'] }}</p>
                @endif

                <div class="bank-page__actions">
                    <a href="{{ route('donate.index') }}" class="bank-page__donate">Donate Online</a>
                    @if (! empty($contact['email']))
                        <a href="mailto:{{ $contact['email'] }}" class="bank-page__email">Email us</a>
                    @endif
                    @if (! empty($contact['phone_primary']))
                        <a href="tel:{{ preg_replace('/\D+/', '', $contact['phone_primary']) }}" class="bank-page__email">
                            {{ $contact['phone_primary'] }}
                        </a>
                    @endif
                </div>
            </div>

            <aside class="bank-page__aside">
                @if (! empty($branding['upiQrUrl']))
                    <div class="bank-page__qr-card">
                        <p class="bank-page__label">Scan to pay</p>
                        <img
                            src="{{ $branding['upiQrUrl'] }}"
                            alt="UPI QR code for donation"
                            width="220"
                            height="220"
                            loading="lazy"
                            class="bank-page__qr"
                        >
                        <p class="bank-page__qr-hint">Open GPay, PhonePe, Paytm or any UPI app and scan this code.</p>
                    </div>
                @endif

                <div class="bank-page__help">
                    <p class="bank-page__label">Need help?</p>
                    <p class="bank-page__help-text">
                        After payment, share the screenshot on WhatsApp or email so we can send your receipt.
                    </p>
                    @if (! empty($branding['social']['whatsapp']))
                        <a href="{{ $branding['social']['whatsapp'] }}" class="bank-page__whatsapp" target="_blank" rel="noopener noreferrer">
                            Chat on WhatsApp
                        </a>
                    @endif
                </div>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (function () {
        const feedback = (button, label) => {
            const original = button.textContent;
            button.textContent = label;
            button.classList.add('is-copied');
            window.setTimeout(() => {
                button.textContent = original;
                button.classList.remove('is-copied');
            }, 1600);
        };

        const copyText = async (text) => {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return;
            }

            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'absolute';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
        };

        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await copyText(button.getAttribute('data-copy') || '');
                    feedback(button, 'Copied');
                } catch (error) {
                    feedback(button, 'Failed');
                }
            });
        });

        const copyAll = document.querySelector('[data-copy-all]');
        if (copyAll) {
            copyAll.addEventListener('click', async () => {
                const lines = Array.from(document.querySelectorAll('.bank-page__row')).map((row) => {
                    const label = row.querySelector('.bank-page__label')?.textContent?.trim() || '';
                    const value = row.querySelector('[data-copy-value]')?.textContent?.trim() || '';
                    return label && value ? `${label}: ${value}` : '';
                }).filter(Boolean).join('\n');

                try {
                    await copyText(lines);
                    feedback(copyAll, 'Copied');
                } catch (error) {
                    feedback(copyAll, 'Failed');
                }
            });
        }
    })();
</script>
@endpush
