@props([
    'label',
    'value',
    'tone' => 'primary',
])

@php
    $toneClass = match ($tone) {
        'success' => 'admin-stat-card--success',
        'warning' => 'admin-stat-card--warning',
        'neutral' => 'admin-stat-card--neutral',
        default => 'admin-stat-card--primary',
    };
@endphp

<div {{ $attributes->merge(['class' => "admin-stat-card {$toneClass}"]) }}>
    <div class="admin-stat-card__value">{{ $value }}</div>
    <div class="admin-stat-card__label">{{ $label }}</div>
</div>
