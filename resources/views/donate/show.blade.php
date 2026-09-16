@extends('layouts.donate')

@php
    $causeSummary = $cause->excerpt ?: $cause->description;
    $causeOgImage = $defaultPackage?->image ?: $cause->hero_image;
@endphp

@section('title', $cause->title . ' | ' . $branding['name'])

@section('meta_description', \App\Support\Seo::metaDescription($causeSummary))

@section('canonical', \App\Support\DonationPublicFrontend::donateCauseUrl($cause->slug))

@section('og_image', \App\Support\Seo::absoluteImageUrl($causeOgImage))

@section('og_image_alt', $cause->title)

@push('structured_data')
    {!! \App\Support\Seo::jsonLdTag(\App\Support\Seo::causePageSchemas($cause, $causeOgImage)) !!}
@endpush

@section('content')
    @php
        $defaultImage = $defaultPackage?->image ?: $cause->hero_image;
        $subscriptionsEnabled = (bool) config('payments.razorpay.subscriptions_enabled');
        $allowedFrequencies = $subscriptionsEnabled ? $cause->allowedRecurringFrequencies() : [];
        $recurringEnabled = $subscriptionsEnabled && $cause->allowsAnyRecurring();
        $hasRecurringPackages = $recurringEnabled && $packages->contains(fn ($package) => $package->allow_recurring);
        $showRecurringOption = $recurringEnabled && ($hasRecurringPackages || $cause->allow_custom_amount);
        $defaultRecurringFrequency = in_array(\App\Support\SubscriptionFrequency::MONTHLY, $allowedFrequencies, true)
            ? \App\Support\SubscriptionFrequency::MONTHLY
            : (\App\Support\SubscriptionFrequency::WEEKLY);
    @endphp
    <div class="donation-grid py-4">
        <nav aria-label="Breadcrumb" class="mb-3">
            <ol class="breadcrumb cause-breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('donate.index') }}">All Causes</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $cause->title }}</li>
            </ol>
        </nav>

        <h1 class="cause-page-title mb-3">{{ $cause->title }}</h1>

        @if (! empty($causeSummary))
            <p class="cause-page-summary text-muted mb-4">{{ \App\Support\Seo::metaDescription($causeSummary) }}</p>
        @endif

        <div class="row">
            <div class="col-lg-6 d-flex flex-column">
                <div class="DonationItem mb-3">
                    <div class="seva-section d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h2 class="mb-0 h5">Select Donation Option</h2>

                        @if(isset($otherCauses) && $otherCauses->isNotEmpty())
                        <div class="cause-switcher">
                            @foreach($otherCauses as $other)
                                @php
                                    $isActive = $other->slug === $cause->slug;
                                    $iconUri = $other->icon_uri;
                                    $activeIconUri = $other->icon_uri_active ?? null;
                                    $icon = $other->icon_uri ? null : ($causeIcons[$other->slug] ?? 'bi-lightning-fill');
                                @endphp
                                <a href="{{ route('donate.show', $other->slug) }}"
                                    class="btn btn-outline-secondary btn-cause {{ $isActive ? 'active' : '' }}"
                                    title="Donate to {{ $other->title }}"
                                    aria-label="Donate to {{ $other->title }}"
                                    @if ($isActive) aria-current="page" @endif>
                                    @if ($iconUri)
                                        @php
                                            $effectiveIconUri = $isActive && ! empty($activeIconUri) ? $activeIconUri : $iconUri;
                                            $src = Illuminate\Support\Str::startsWith($effectiveIconUri, ['http://', 'https://'])
                                                ? $effectiveIconUri
                                                : asset($effectiveIconUri);
                                        @endphp
                                        <img src="{{ $src }}" alt="" class="cause-icon" loading="lazy" decoding="async" />
                                    @else
                                        <i class="bi {{ $icon }} {{ $isActive ? 'text-white' : '' }}" aria-hidden="true"></i>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div class="row g-1">
                        @forelse ($packages as $package)
                            <div class="col-md-6 col-lg-12 col-xl-6">
                                <div
                                    class="seva-pill @if ($defaultPackage && $package->id === $defaultPackage->id) active @endif"
                                    data-package-id="{{ $package->id }}"
                                    data-title="{{ $package->title}}"
                                    data-amount="{{ $package->amount }}"
                                    data-image="{{ $package->image ? asset($package->image) : '' }}"
                                    data-meta='@json($package->meta ?? [])'
                                    data-allow-recurring="{{ ($recurringEnabled && $package->allow_recurring) ? '1' : '0' }}"
                                >
                                    <div class="seva-label">{{ ucwords($package->title) }}</div>
                                    <div class="seva-price">₹ {{ number_format((float) $package->amount) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <p class="text-muted mb-0">
                                    Choose your donation amount on the right.
                                </p>
                            </div>
                        @endforelse
                    </div>
                    @if (! empty($cause->details))
                        <div class="col-12">
                            {{-- <ul class="mt-3 Ft-Guj"> --}}
                            <ul class="mt-3">
                                @foreach ($cause->details as $detail)
                                    <li>{{ $detail }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="cause-image d-none d-lg-block">
                    @if ($defaultImage)
                        <img id="sevaImage" src="{{ asset($defaultImage) }}" alt="{{ $cause->title }}" loading="eager" decoding="async">
                    @endif
                </div>
                @if ($cause->hasContactCard())
                    @include('donate.partials.cause-contact-card', ['cause' => $cause])
                @endif
            </div>
            <div class="col-lg-6">
                <div class="DonationWrapper">
                    <div class="DonateTab mb-4">
                        <ul class="nav nav-pills" id="pills-tab" role="tablist">
                          <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="indiandonation-tab" data-bs-toggle="pill" data-bs-target="#indiandonation" type="button" role="tab" aria-controls="indiandonation" aria-selected="true">Donate from India</button>
                          </li>
                          <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bonateoutsideindia-tab" data-bs-toggle="pill" data-bs-target="#bonateoutsideindia" type="button" role="tab" aria-controls="bonateoutsideindia" aria-selected="false">Donate from Outside India</button>
                          </li>
                        </ul>
                    </div>
                    <div class="tab-content" id="pills-tabContent">
                        <div class="tab-pane fade show active" id="indiandonation" role="tabpanel" aria-labelledby="indiandonation-tab" tabindex="0">
                            <div id="indiaDonationSection">
                                @include('donate.partials.donation-form', [
                                    'cause' => $cause->slug,
                                    'causeLabel' => $cause->title,
                                    'defaultTitle' => $defaultTitle,
                                    'defaultAmount' => $defaultAmount,
                                    'ctaText' => $cause->cta_text ?? 'Donate',
                                    'panCollectionEnabled' => $cause->pan_required,
                                    'allowCustomAmount' => $cause->allow_custom_amount,
                                    'defaultPackageId' => $defaultPackage?->id,
                                    'showRecurringOption' => $showRecurringOption,
                                    'allowedFrequencies' => $allowedFrequencies,
                                    'defaultFrequency' => $defaultRecurringFrequency,
                                ])
                            </div>
                        </div>
                        <div class="tab-pane fade" id="bonateoutsideindia" role="tabpanel" aria-labelledby="bonateoutsideindia-tab" tabindex="0">
                            <div id="internationalDonationSection">
                                <div class="donation-form text-center">
                                    <h3>Donate from Outside India</h3>
                                    <p class="text-muted">
                                        International donations are securely handled by Danamojo.
                                    </p>

                                    <a href="{{ rtrim(config('donation.public_frontend_url'), '/') }}/donate/danamojo-widget/{{ $cause->slug }}"
                                        class="btn btn-primary btn-lg w-100">
                                        Donate via Danamojo
                                    </a>

                                    
                                </div>
                            </div>
                        </div>
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
