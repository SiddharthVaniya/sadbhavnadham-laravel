@extends('layouts.donate')

@section('title', 'Donate | ' . $branding['name'])

@section('meta_description', \App\Support\Seo::metaDescription(config('branding.seo.home_description')))

@section('canonical', \App\Support\DonationPublicFrontend::homeUrl())

@if ($suppressIndexing ?? false)
@section('robots', 'noindex, nofollow')
@endif

@push('structured_data')
    {!! \App\Support\Seo::jsonLdTag(\App\Support\Seo::homePageSchemas($causes)) !!}
@endpush

@section('content')
<section class="donate-landing">
    <div class="landing-header text-center">
        <h1>Choose a Cause to Support</h1>
        <p>
            Your contribution helps us serve humanity, protect <br>nature,
            and care for animals in need.
        </p>
    </div>
    <div class="row g-3 g-md-4">
        @foreach ($causes as $cause)
            @php($isFirstCause = $loop->first)
            <div class="col-12 col-sm-6 col-lg-4 d-flex">
                <a class="DonateItem w-100 h-100 d-flex flex-column" href="{{ route('donate.show', ['cause' => $cause->slug]) }}">
                    <div class="DonateThumb">
                        <div class="swiper mySwiper" data-delay="{{ 1200 + ($loop->index * 300) }}">
                            <div class="swiper-wrapper">
                                @forelse (($cause->images ?? []) as $image)
                                    <div class="swiper-slide">
                                        <img class="img-fluid w-100" src="{{ asset($image) }}" alt="{{ $cause->title }}" loading="{{ $isFirstCause && $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                                    </div>
                                @empty
                                    @if ($cause->hero_image)
                                        <div class="swiper-slide">
                                            <img class="img-fluid w-100" src="{{ asset($cause->hero_image) }}" alt="{{ $cause->title }}" loading="{{ $isFirstCause ? 'eager' : 'lazy' }}" decoding="async">
                                        </div>
                                    @endif
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="DonateContent">
                        <h2 class="cause-card-title h4">{{ $cause->title }}</h2>
                        @if ($cause->excerpt)
                            <p class="mb-0">{!! nl2br(e($cause->excerpt)) !!}</p>
                        @endif
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</section>
@endsection
