@extends('layouts.donate')

@section('title', 'Thank you | ' . $branding['shortName'])

@section('robots', 'noindex, nofollow')

@section('meta_description', 'Thank you for supporting ' . $branding['name'] . '.')

@section('canonical', \App\Support\Seo::canonicalUrl(route('donate.index', [], false)))

@section('content')
<section class="thank-you-page" aria-labelledby="thankYouHeading">
    <div class="thank-you-shell">
        <div class="thank-you-mark" aria-hidden="true">
            <svg viewBox="0 0 52 52" class="thank-you-check">
                <circle class="thank-you-check-circle" cx="26" cy="26" r="24" fill="none" />
                <path class="thank-you-check-path" fill="none" d="M14.5 27.5l7 7 16-16" />
            </svg>
        </div>

        <h1 id="thankYouHeading" class="thank-you-heading">{{ $thankYou['headline'] }}</h1>
        <p class="thank-you-subcopy">{{ $thankYou['subcopy'] }}</p>

        <div class="thank-you-amount" aria-label="Donation amount">
            {{ $thankYou['amount_label'] }}
        </div>

        <dl class="thank-you-meta">
            @if ($thankYou['donor_name'])
                <div>
                    <dt>Donor</dt>
                    <dd>{{ $thankYou['donor_name'] }}</dd>
                </div>
            @endif
            @if ($thankYou['cause_title'])
                <div>
                    <dt>Cause</dt>
                    <dd>{{ $thankYou['cause_title'] }}</dd>
                </div>
            @endif
            @if ($thankYou['item_title'] && $thankYou['item_title'] !== $thankYou['cause_title'])
                <div>
                    <dt>For</dt>
                    <dd>{{ $thankYou['item_title'] }}</dd>
                </div>
            @endif
            @if ($thankYou['receipt_number'])
                <div>
                    <dt>Receipt</dt>
                    <dd>{{ $thankYou['receipt_number'] }}</dd>
                </div>
            @endif
            <div>
                <dt>Reference</dt>
                <dd class="thank-you-reference">{{ $thankYou['reference'] }}</dd>
            </div>
        </dl>

        @unless ($thankYou['is_confirmed'])
            <p class="thank-you-note">Confirmation usually completes within a few minutes.</p>
        @endunless

        <div class="thank-you-actions">
            <a href="{{ $thankYou['back_url'] }}" class="thank-you-btn thank-you-btn-primary">Back to causes</a>
            @if (! empty($branding['urls']['website']))
                <a href="{{ $branding['urls']['website'] }}" class="thank-you-btn thank-you-btn-secondary">Visit website</a>
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    var ecommerce = @json($thankYou['ecommerce']);
    var transactionId = ecommerce && ecommerce.transaction_id ? String(ecommerce.transaction_id) : '';
    if (!transactionId) {
        return;
    }

    var storageKey = 'donation_purchase_' + transactionId;
    try {
        if (window.sessionStorage && sessionStorage.getItem(storageKey)) {
            return;
        }
        if (window.sessionStorage) {
            sessionStorage.setItem(storageKey, '1');
        }
    } catch (e) {}

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push({
        event: 'purchase',
        ecommerce: ecommerce
    });

    if (typeof window.fbq === 'function') {
        window.fbq('track', 'Purchase', {
            value: ecommerce.value,
            currency: ecommerce.currency,
            content_type: 'donation',
            contents: (ecommerce.items || []).map(function (item) {
                return {
                    id: item.item_id,
                    quantity: item.quantity,
                    item_price: item.price
                };
            })
        });
    }
})();
</script>
@endpush
