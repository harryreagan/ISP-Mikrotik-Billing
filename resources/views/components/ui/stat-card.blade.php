@props(['label' => '', 'value' => '', 'sub' => '', 'color' => 'purple', 'icon' => ''])

@php
    $gradients = [
        'purple' => 'linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%)',
        'blue'   => 'linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%)',
        'orange' => 'linear-gradient(135deg, #f59e0b 0%, #ea580c 100%)',
        'green'  => 'linear-gradient(135deg, #10b981 0%, #16a34a 100%)',
        'navy'   => 'linear-gradient(135deg, #1e293b 0%, #1e1b3a 100%)',
        'pink'   => 'linear-gradient(135deg, #ec4899 0%, #e11d48 100%)',
    ];
    $bgStyle = $gradients[$color] ?? $gradients['purple'];
@endphp

<div {{ $attributes->merge(['class' => 'ui-stat-card rounded-4 p-4 text-white d-flex align-items-start justify-content-between gap-3 h-100']) }} style="background: {{ $bgStyle }};">
    <div class="min-w-0 flex-grow-1">
        <div class="ui-stat-label">{{ $label }}</div>
        <div class="ui-stat-value mt-1">{{ $value }}</div>
        @if($sub)
            <div class="ui-stat-sub mt-1">{{ $sub }}</div>
        @endif
        @if(isset($slot) && !empty(trim((string)$slot)))
            <div class="mt-2 pt-2 border-top border-white border-opacity-10 small">
                {{ $slot }}
            </div>
        @endif
    </div>
    @if(!empty($icon))
        <div class="ui-stat-icon flex-shrink-0">
            @if(str_contains((string)$icon, '<'))
                {!! $icon !!}
            @else
                <i class="{{ str_contains((string)$icon, 'bi') ? $icon : 'bi bi-' . $icon }}"></i>
            @endif
        </div>
    @elseif(isset($iconSlot))
        <div class="ui-stat-icon flex-shrink-0">
            {{ $iconSlot }}
        </div>
    @endif
</div>

