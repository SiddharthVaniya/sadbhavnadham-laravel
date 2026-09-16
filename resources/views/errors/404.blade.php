@extends('layouts.donate')

@section('title', 'Page Not Found | ' . $branding['name'])

@section('meta_description', 'The page you are looking for could not be found. Browse our causes and donate today.')

@section('robots', 'noindex, nofollow')

@section('canonical', \App\Support\Seo::canonicalUrl(route('donate.index', [], false)))

@section('content')
    <section class="error-page text-center py-5">
        <div class="landing-header">
            <p class="text-uppercase text-muted mb-2" style="letter-spacing: 0.08em;">404</p>
            <h1 class="mb-3">Page Not Found</h1>
            <p class="text-muted mb-4">
                The link may be broken or the page may have been moved.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                <a href="{{ route('donate.index') }}" class="btn btn-primary">View All Causes</a>
                @if ($branding['urls']['website'])
                    <a href="{{ $branding['urls']['website'] }}" class="btn btn-outline-secondary">Visit Main Website</a>
                @endif
            </div>

            @if (($featuredCauses ?? collect())->isNotEmpty())
                <div class="text-start mx-auto" style="max-width: 42rem;">
                    <h2 class="h5 text-center mb-3">Popular causes</h2>
                    <ul class="list-group list-group-flush shadow-sm rounded">
                        @foreach ($featuredCauses as $featuredCause)
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <a href="{{ route('donate.show', $featuredCause->slug) }}" class="fw-semibold text-decoration-none">
                                        {{ $featuredCause->title }}
                                    </a>
                                    @if ($featuredCause->excerpt)
                                        <p class="mb-0 small text-muted">{{ \App\Support\Seo::metaDescription($featuredCause->excerpt) }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('donate.show', $featuredCause->slug) }}" class="btn btn-sm btn-outline-primary">Donate</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endsection
