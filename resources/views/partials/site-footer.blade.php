@php
    $contact = $branding['contact'] ?? [];
    $social = $branding['social'] ?? [];
    $usefulLinks = $branding['usefulLinks'] ?? [];
    $initiativeLinks = $branding['initiativeLinks'] ?? [];
    $bank = $branding['bank'] ?? [];
    $phonePrimaryDigits = preg_replace('/\D+/', '', (string) ($contact['phone_primary'] ?? '')) ?: null;
    $phoneSecondaryDigits = preg_replace('/\D+/', '', (string) ($contact['phone_secondary'] ?? '')) ?: null;
    $footerBankRows = array_values(array_filter([
        ['label' => 'A/C Name', 'value' => $bank['account_name'] ?? ''],
        ['label' => 'A/C No.', 'value' => $bank['account_number'] ?? ''],
        ['label' => 'IFSC', 'value' => $bank['ifsc'] ?? ''],
        ['label' => 'Bank', 'value' => $bank['bank_name'] ?? ''],
        ['label' => 'UPI', 'value' => $bank['upi_id'] ?? ''],
    ], fn ($row) => filled($row['value'])));
@endphp

<footer class="site-footer">
    <div class="container">
        <div class="site-footer__panel">
            <div class="site-footer__grid">
                <div class="site-footer__brand">
                    <a href="{{ $branding['urls']['website'] ?: route('donate.index') }}" class="site-footer__logo">
                        <img src="{{ $branding['logoPublicUrl'] }}" alt="{{ $branding['name'] }}">
                    </a>

                    @if (! empty($branding['footerAbout']))
                        <p class="site-footer__about">{{ $branding['footerAbout'] }}</p>
                    @endif

                    <div class="site-footer__social">
                        @if (! empty($social['facebook']))
                            <a href="{{ $social['facebook'] }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg>
                            </a>
                        @endif
                        @if (! empty($social['instagram']))
                            <a href="{{ $social['instagram'] }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6.5-.9a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                            </a>
                        @endif
                        @if (! empty($social['youtube']))
                            <a href="{{ $social['youtube'] }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23 12.2s0-3.2-.4-4.7c-.2-.8-.9-1.5-1.7-1.7C19.4 5.4 12 5.4 12 5.4s-7.4 0-8.9.4c-.8.2-1.5.9-1.7 1.7C1 9 1 12.2 1 12.2s0 3.2.4 4.7c.2.8.9 1.5 1.7 1.7 1.5.4 8.9.4 8.9.4s7.4 0 8.9-.4c.8-.2 1.5-.9 1.7-1.7.4-1.5.4-4.7.4-4.7zM9.8 15.5v-6.6l6.2 3.3-6.2 3.3z"/></svg>
                            </a>
                        @endif
                        @if (! empty($social['whatsapp']))
                            <a href="{{ $social['whatsapp'] }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.04 2C6.58 2 2.15 6.4 2.15 11.84c0 1.94.56 3.75 1.53 5.28L2 22l5.05-1.63a9.86 9.86 0 0 0 4.99 1.34h.01c5.46 0 9.89-4.4 9.89-9.87C21.94 6.4 17.5 2 12.04 2zm5.75 14.06c-.24.67-1.4 1.24-1.93 1.32-.5.07-1.13.1-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.78-4.17-4.92-4.36-.14-.19-1.15-1.53-1.15-2.92 0-1.39.73-2.07.99-2.35.26-.28.57-.35.76-.35h.55c.17 0 .4-.07.63.48.24.56.81 1.94.88 2.08.07.14.12.3.02.49-.1.19-.14.3-.28.47-.14.16-.3.36-.42.49-.14.14-.28.29-.12.57.16.28.71 1.17 1.52 1.9 1.05.94 1.93 1.23 2.21 1.37.28.14.44.12.6-.07.17-.19.7-.81.88-1.09.19-.28.37-.23.63-.14.26.09 1.64.77 1.92.91.28.14.47.21.54.33.07.12.07.7-.17 1.37z"/></svg>
                            </a>
                        @endif
                    </div>

                    @if (! empty($branding['upiQrUrl']))
                        <div class="site-footer__qr">
                            <img src="{{ $branding['upiQrUrl'] }}" alt="Scan to donate via UPI" width="168" height="168" loading="lazy">
                        </div>
                    @endif
                </div>

                <div class="site-footer__col">
                    <h3 class="site-footer__heading">Useful Links</h3>
                    <ul class="site-footer__links">
                        @foreach ($usefulLinks as $link)
                            <li><a href="{{ $link['url'] }}" @if (str_starts_with($link['url'], 'http')) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div class="site-footer__col">
                    <h3 class="site-footer__heading">Initiatives</h3>
                    <ul class="site-footer__links">
                        @foreach ($initiativeLinks as $link)
                            <li><a href="{{ $link['url'] }}" @if (str_starts_with($link['url'], 'http')) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div class="site-footer__col site-footer__col--contact">
                    <h3 class="site-footer__heading">Stay Connected</h3>
                    @if (! empty($contact['address']))
                        <p class="site-footer__contact-text">{{ $contact['address'] }}</p>
                    @endif
                    <ul class="site-footer__contact-list">
                        @if (! empty($contact['phone_primary']))
                            <li>
                                <a href="tel:{{ $phonePrimaryDigits }}">{{ $contact['phone_primary'] }}</a>
                            </li>
                        @endif
                        @if (! empty($contact['phone_secondary']))
                            <li>
                                <a href="tel:{{ $phoneSecondaryDigits }}">{{ $contact['phone_secondary'] }}</a>
                            </li>
                        @endif
                        @if (! empty($contact['email']))
                            <li>
                                <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            @if ($footerBankRows !== [])
                <div class="site-footer__bank">
                    <div class="site-footer__bank-aside">
                        <p class="site-footer__bank-kicker">Donate via transfer</p>
                        <h3 class="site-footer__bank-title">Bank Details</h3>
                        <a href="{{ route('donate.bank-details') }}" class="site-footer__bank-more">Open full page</a>
                    </div>
                    <ul class="site-footer__bank-list">
                        @foreach ($footerBankRows as $row)
                            <li class="site-footer__bank-row">
                                <div class="site-footer__bank-meta">
                                    <span class="site-footer__bank-label">{{ $row['label'] }}</span>
                                    <strong class="site-footer__bank-value">{{ $row['value'] }}</strong>
                                </div>
                                <button
                                    type="button"
                                    class="site-footer__bank-copy"
                                    data-footer-copy="{{ $row['value'] }}"
                                    aria-label="Copy {{ $row['label'] }}"
                                >
                                    <span class="site-footer__bank-copy-label">Copy</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="site-footer__bottom">
                <div class="site-footer__legal">
                    @if ($branding['urls']['privacy'])
                        <a href="{{ $branding['urls']['privacy'] }}">Privacy Policy</a>
                    @endif
                    @if ($branding['urls']['terms'])
                        <a href="{{ $branding['urls']['terms'] }}">Terms &amp; Conditions</a>
                    @endif
                    @if ($branding['urls']['refund'])
                        <a href="{{ $branding['urls']['refund'] }}">Refund and Cancellation</a>
                    @endif
                    <span class="site-footer__copy">© {{ date('Y') }} {{ $branding['legalName'] }}</span>
                </div>
                @if (! empty($branding['paymentGatewaysUrl']))
                    <div class="site-footer__payments">
                        <img src="{{ $branding['paymentGatewaysUrl'] }}" alt="Accepted payment methods" loading="lazy">
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>

@once
    @push('scripts')
        <script>
            (function () {
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

                document.querySelectorAll('[data-footer-copy]').forEach((button) => {
                    button.addEventListener('click', async () => {
                        const label = button.querySelector('.site-footer__bank-copy-label') || button;
                        const original = label.textContent;
                        try {
                            await copyText(button.getAttribute('data-footer-copy') || '');
                            label.textContent = 'Copied';
                            button.classList.add('is-copied');
                        } catch (error) {
                            label.textContent = 'Failed';
                        }
                        window.setTimeout(() => {
                            label.textContent = original;
                            button.classList.remove('is-copied');
                        }, 1400);
                    });
                });
            })();
        </script>
    @endpush
@endonce
