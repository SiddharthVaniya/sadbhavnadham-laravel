@php
    $heading = $cause->contact_heading ?: 'Contact & Address';
@endphp

<aside class="cause-contact-card" aria-label="{{ $heading }}">
    <h2 class="cause-contact-card__title">{{ $heading }}</h2>

    @if (! empty($cause->contact_address))
        <p class="cause-contact-card__address">{!! nl2br(e($cause->contact_address)) !!}</p>
    @endif

    <ul class="cause-contact-card__list">
        @if (! empty($cause->contact_phone))
            <li>
                <span class="cause-contact-card__label">Phone</span>
                <a href="tel:{{ preg_replace('/\D+/', '', $cause->contact_phone) }}">{{ $cause->contact_phone }}</a>
            </li>
        @endif
        @if (! empty($cause->contact_email))
            <li>
                <span class="cause-contact-card__label">Email</span>
                <a href="mailto:{{ $cause->contact_email }}">{{ $cause->contact_email }}</a>
            </li>
        @endif
    </ul>
</aside>
