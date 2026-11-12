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
    <div class="min-w-0">
        <div class="ui-stat-label">{{ $label }}</div>
        <div class="ui-stat-value mt-1">{{ $value }}</div>
        @if($sub)
            <div class="ui-stat-sub mt-1">{{ $sub }}</div>
        @endif
    </div>
    @if($icon)
        <div class="ui-stat-icon">
            <i class="bi {{ $icon }}"></i>
        </div>
    @elseif(isset($iconSlot))
        <div class="ui-stat-icon">
            {{ $iconSlot }}
        </div>
    @endif
</div>

