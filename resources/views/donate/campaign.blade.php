@extends('layouts.donate')

@php
    $pageTitle = $campaign->headline ?: $campaign->name;
    $pageSummary = $campaign->subheadline ?: ($cause->excerpt ?: $cause->description);
    $campaignOgImage = $heroImage ?? $cause->hero_image;
    $isRecurringOnly = (bool) $campaign->recurring_only;
    $billingFrequency = $isRecurringOnly ? $campaign->billingFrequency() : 'monthly';
    $giftLabel = $isRecurringOnly
        ? \App\Support\SubscriptionFrequency::giftLabel($billingFrequency)
        : 'One-time gift';
    $periodLabel = $isRecurringOnly
        ? \App\Support\SubscriptionFrequency::periodLabel($billingFrequency)
        : 'one time';
    $ctaText = $isRecurringOnly
        ? \App\Support\SubscriptionFrequency::ctaLabel($billingFrequency)
        : ($cause->cta_text ?? 'Donate Now');
@endphp

@section('title', $pageTitle . ' | ' . $branding['name'])

@section('meta_description', \App\Support\Seo::metaDescription($pageSummary))

@section('canonical', \App\Support\Seo::canonicalUrl(route('donate.campaign', $campaign->slug, false)))

@section('og_image', \App\Support\Seo::absoluteImageUrl($campaignOgImage))

@section('og_image_alt', $pageTitle)

@push('structured_data')
    {!! \App\Support\Seo::jsonLdTag(\App\Support\Seo::causePageSchemas($cause, $campaignOgImage)) !!}
@endpush

@section('content')
    <div class="donation-grid py-4">
        <nav aria-label="Breadcrumb" class="mb-3">
            <ol class="breadcrumb cause-breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('donate.index') }}">All Causes</a></li>
                <li class="breadcrumb-item"><a href="{{ route('donate.show', $cause->slug) }}">{{ $cause->title }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $campaign->name }}</li>
            </ol>
        </nav>

        <h1 class="cause-page-title mb-3">{{ $pageTitle }}</h1>

        @if (! empty($pageSummary))
            <p class="cause-page-summary text-muted mb-4">{{ \App\Support\Seo::metaDescription($pageSummary) }}</p>
        @endif

        <div class="row">
            <div class="col-lg-6 d-flex flex-column">
                <div class="DonationItem mb-3">
                    <div class="campaign-amount-card rounded-3 border p-4 mb-3">
                        <p class="text-uppercase text-muted small mb-2" id="campaignGiftLabel">{{ $giftLabel }}</p>
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <span class="display-6 fw-semibold mb-0">₹ {{ number_format((float) $defaultAmount) }}</span>
                            <span class="text-muted pb-1" id="campaignPeriodLabel">{{ $periodLabel }}</span>
                        </div>
                        <p class="mb-0 mt-2 text-muted">
                            Supporting <strong>{{ $cause->title }}</strong>
                            @if ($defaultTitle)
                                — {{ $defaultTitle }}
                            @endif
                        </p>
                    </div>

                    @if ($goalAmount)
                        <div class="campaign-goal-card rounded-3 border p-4 mb-3" aria-label="Campaign fundraising progress">
                            <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                                <span class="text-uppercase text-muted small mb-0">Campaign progress</span>
                                <span class="fw-semibold">{{ number_format((float) $goalProgressPercent, 1) }}%</span>
                            </div>
                            <div class="progress mb-2" style="height: 0.55rem;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round($goalProgressPercent) }}">
                                <div class="progress-bar bg-success" style="width: {{ $goalProgressPercent }}%;"></div>
                            </div>
                            <p class="mb-0 small text-muted">
                                ₹{{ number_format((float) $raisedAmount) }} raised of ₹{{ number_format((float) $goalAmount) }} goal
                            </p>
                        </div>
                    @endif

                    @if (! empty($cause->details))
                        <ul class="mt-2">
                            @foreach ($cause->details as $detail)
                                <li>{{ $detail }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if (! empty($heroImage))
                    <div class="cause-image d-none d-lg-block">
                        <img src="{{ asset($heroImage) }}" alt="{{ $cause->title }}" loading="eager" decoding="async">
                    </div>
                @endif

                @if ($cause->hasContactCard())
                    @include('donate.partials.cause-contact-card', ['cause' => $cause])
                @endif
            </div>

            <div class="col-lg-6">
                <div class="DonationWrapper">
                    <div id="indiaDonationSection">
                        @include('donate.partials.donation-form', [
                            'cause' => $cause->slug,
                            'causeLabel' => $cause->title,
                            'defaultTitle' => $defaultTitle,
                            'defaultAmount' => $defaultAmount,
                            'ctaText' => $ctaText,
                            'panCollectionEnabled' => $cause->pan_required,
                            'allowCustomAmount' => false,
                            'defaultPackageId' => $defaultPackage?->id,
                            'showRecurringOption' => $isRecurringOnly,
                            'forceRecurring' => $isRecurringOnly,
                            'defaultFrequency' => $billingFrequency,
                            'allowedFrequencies' => [$billingFrequency],
                            'lockAmount' => true,
                            'campaignSlug' => $campaign->slug,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="{{ \App\Support\PublicAsset::url('js/donation-razorpay.js') }}"></script>
    <script src="{{ \App\Support\PublicAsset::url('js/donate.js') }}"></script>
@endpush
