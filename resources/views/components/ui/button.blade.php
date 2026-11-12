@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
    $variantClass = match($variant) {
        'primary'   => 'ui-btn-primary',
        'secondary' => 'ui-btn-secondary',
        'danger'    => 'ui-btn-danger',
        'ghost'     => 'ui-btn-ghost',
        default     => 'ui-btn-primary',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "ui-btn {$variantClass} text-decoration-none d-inline-flex align-items-center justify-content-center"]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "ui-btn {$variantClass}"]) }}>
        {{ $slot }}
    </button>
@endif
