@extends('layouts.donate')

@section('title', 'My donations | ' . $branding['shortName'])

@section('robots', 'noindex, nofollow')

@section('meta_description', 'View your donation history and receipts with ' . $branding['name'] . '.')

@section('canonical', \App\Support\Seo::canonicalUrl(route('donate.portal.index', [], false)))

@section('content')
<section class="donor-portal" aria-labelledby="donorPortalHeading">
    <div class="donor-portal__header">
        <div>
            <p class="donor-portal__eyebrow">Donor account</p>
            <h1 id="donorPortalHeading">My donations</h1>
            <p class="donor-portal__lead">
                Signed in as <strong>{{ $donor->name ?: ($donorPortal['display_name'] ?? 'Donor') }}</strong>.
                Review past donations and open receipts securely.
            </p>
        </div>
        <a href="{{ route('donate.index') }}" class="donor-portal__cta">Donate again</a>
    </div>

    @if ($orders->isEmpty())
        <div class="donor-portal__empty">
            <h2>No paid donations yet</h2>
            <p>When you complete a donation while signed in with this mobile number, it will appear here.</p>
            <a href="{{ route('donate.index') }}" class="donor-portal__cta">Browse causes</a>
        </div>
    @else
        <div class="donor-portal__summary" aria-label="Donation summary">
            <div class="donor-portal__summary-item">
                <span class="donor-portal__summary-value">{{ number_format($summary['count']) }}</span>
                <span class="donor-portal__summary-label">{{ $summary['count'] === 1 ? 'Donation' : 'Donations' }}</span>
            </div>
            <div class="donor-portal__summary-divider" aria-hidden="true"></div>
            <div class="donor-portal__summary-item">
                <span class="donor-portal__summary-value">{{ $summary['total_label'] }}</span>
                <span class="donor-portal__summary-label">Total supported</span>
            </div>
        </div>

        <div class="donor-portal__list" role="list">
            @foreach ($orders as $order)
                @php($card = \App\Support\DonorPortalData::orderCard($order))
                <article class="donor-portal__card" role="listitem">
                    <div class="donor-portal__card-accent" aria-hidden="true"></div>
                    <div class="donor-portal__card-body">
                        <div class="donor-portal__card-top">
                            <div class="donor-portal__copy">
                                <div class="donor-portal__title-row">
                                    <h2 class="donor-portal__cause">{{ $card['cause_title'] }}</h2>
                                    @if ($card['is_recurring'])
                                        <span class="donor-portal__badge">Recurring</span>
                                    @endif
                                </div>
                                @if ($card['item_title'])
                                    <p class="donor-portal__package">{{ $card['item_title'] }}</p>
                                @endif
                            </div>
                            <div class="donor-portal__amount">{{ $card['amount_label'] }}</div>
                        </div>

                        <div class="donor-portal__card-bottom">
                            <p class="donor-portal__meta">
                                @if ($card['date_label'])
                                    <span>{{ $card['date_label'] }}</span>
                                @endif
                                @if ($card['receipt_label'])
                                    <span class="donor-portal__meta-sep" aria-hidden="true">·</span>
                                    <span>Receipt {{ $card['receipt_label'] }}</span>
                                @endif
                            </p>
                            <a href="{{ route('donate.portal.receipt', $order) }}" class="donor-portal__btn">View receipt</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="donor-portal__pager">
            {{ $orders->links() }}
        </div>
    @endif
</section>
@endsection
