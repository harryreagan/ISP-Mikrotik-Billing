@props([
    'color' => 'auto',
    'height' => '38px',
    'showText' => true,
])

@php
    $textColor = match($color) {
        'light' => '#FFFFFF',
        'dark' => '#0F172A',
        default => 'currentColor',
    };
@endphp

<div {{ $attributes->merge(['class' => 'hsmart-brand-logo']) }} style="display: inline-flex; align-items: center; gap: 10px; height: {{ $height }}; text-decoration: none; user-select: none;">
    <!-- Icon Squircle with WiFi Waves -->
    <div style="width: {{ $height }}; height: {{ $height }}; flex-shrink: 0; position: relative;">
        <svg viewBox="0 0 44 44" fill="none" style="width: 100%; height: 100%; display: block;">
            <defs>
                <linearGradient id="brandGrad_{{ md5($height . $color) }}" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FF8A00" />
                    <stop offset="100%" stop-color="#E55A00" />
                </linearGradient>
            </defs>
            <rect x="2" y="2" width="40" height="40" rx="11" fill="url(#brandGrad_{{ md5($height . $color) }})" style="filter: drop-shadow(0 3px 6px rgba(229, 90, 0, 0.35));" />
            <path d="M12.5 15 C16.5 11 27.5 11 31.5 15" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" fill="none" />
            <path d="M15.5 19.5 C18.5 16.5 25.5 16.5 28.5 19.5" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" fill="none" />
            <path d="M18.5 24 C20.5 22 23.5 22 25.5 24" stroke="#FFFFFF" stroke-width="2.6" stroke-linecap="round" fill="none" />
            <circle cx="22" cy="29" r="2.3" fill="#FFFFFF" />
        </svg>
    </div>

    @if($showText)
        <!-- Brand Typography -->
        <div style="display: inline-flex; align-items: center; gap: 8px;">
            <span style="font-family: 'Jost', 'Inter', -apple-system, sans-serif; font-size: calc({{ $height }} * 0.58); font-weight: 800; color: {{ $textColor }}; letter-spacing: -0.03em; line-height: 1;">
                HSmart
            </span>
            <span style="font-family: 'Jost', 'Inter', -apple-system, sans-serif; font-size: calc({{ $height }} * 0.32); font-weight: 800; color: #FFFFFF; background: linear-gradient(135deg, #6366F1, #8B5CF6); padding: 3px 8px; border-radius: 6px; letter-spacing: 0.08em; text-transform: uppercase; line-height: 1.2; box-shadow: 0 2px 5px rgba(99, 102, 241, 0.3);">
                ISP
            </span>
        </div>
    @endif
</div>
