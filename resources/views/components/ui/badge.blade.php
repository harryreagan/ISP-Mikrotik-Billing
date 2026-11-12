@props(['tone' => 'neutral'])

@php
    $toneClass = match($tone) {
        'success' => 'ui-badge-success',
        'warning' => 'ui-badge-warning',
        'danger'  => 'ui-badge-danger',
        'info'    => 'ui-badge-info',
        default   => 'ui-badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => "ui-badge {$toneClass}"]) }}>
    {{ $slot }}
</span>

