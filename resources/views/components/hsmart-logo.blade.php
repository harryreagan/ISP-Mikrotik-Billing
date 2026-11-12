@props([
    'color' => 'auto',
    'height' => '40px',
    'showText' => true,
    'brandName' => null,
    'showSubtitle' => null,
])

@php
    $textColor = match($color) {
        'light' => '#FFFFFF',
        'dark' => '#0F172A',
        default => 'currentColor',
    };

    $subtitleColor = match($color) {
        'light' => '#CBD5E1',
        default => '#64748B',
    };

    $loggedInBusinessName = null;
    if (auth()->check()) {
        $u = auth()->user();
        $tName = $u->tenant?->name ?? null;
        $rComp = $u->reseller?->company ?? null;
        $pBrand = null;
        try {
            if ($u->tenant_id && \Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                $pBrand = \Illuminate\Support\Facades\DB::table('isp_portal_settings')
                    ->where('tenant_id', $u->tenant_id)
                    ->value('brand_name');
            }
        } catch (\Throwable $e) {}

        $teamName = null;
        try {
            if (method_exists($u, 'currentTeam') && $u->currentTeam) {
                $teamName = $u->currentTeam->name;
            } elseif (method_exists($u, 'personalTeam') && $u->personalTeam()) {
                $teamName = $u->personalTeam()->name;
            }
        } catch (\Throwable $e) {}
        $cleanTeamName = $teamName ? trim(preg_replace("/'s\s+Team$/i", '', $teamName)) : null;

        $genericDefaults = ['hsmart', 'hsmart isp', 'hsmart wifi', 'hsmart wifi billing', 'default isp', 'default tenant'];
        $candidates = array_filter([
            $rComp,
            $pBrand,
            $tName,
            $cleanTeamName,
            $u->business_name ?? ($u->company ?? null),
            $u->name,
        ], fn($val) => !empty($val) && !in_array(strtolower(trim((string) $val)), $genericDefaults));

        $found = reset($candidates) ?: ($tName ?: ($u->name ?: null));
        if ($found) {
            $stripped = trim(preg_replace('/^hsmart\s+/i', '', (string) $found));
            $loggedInBusinessName = $stripped !== '' ? $stripped : $found;
        }
    }

    $displayName = $brandName ?: ($loggedInBusinessName ?: 'HSmart');
    $subtitleText = ($showSubtitle ?? auth()->check()) ? (auth()->check() ? 'ISP Management' : 'WiFi Billing Platform') : null;
@endphp

<div {{ $attributes->merge(['class' => 'hsmart-brand-logo']) }} style="display: inline-flex; align-items: center; gap: 12px; min-width: 0; max-width: 100%; text-decoration: none; user-select: none;">
    <!-- 40x40 rounded-xl (#F57C00) orange tile with clean white WiFi-arcs SVG icon (3 arcs + dot, stroke 2.4, round caps) -->
    <div class="brand-logo-tile" style="width: 40px; height: 40px; border-radius: 12px; background-color: #F57C00; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display: block;">
            <path d="M3.5 8.8C8.2 4.5 15.8 4.5 20.5 8.8" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" />
            <path d="M6.5 12.5C9.6 9.7 14.4 9.7 17.5 12.5" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" />
            <path d="M9.5 16.1C11 14.8 13 14.8 14.5 16.1" stroke="#FFFFFF" stroke-width="2.4" stroke-linecap="round" />
            <circle cx="12" cy="19.2" r="1.5" fill="#FFFFFF" />
        </svg>
    </div>

    @if($showText)
        <!-- Two-line text block with min-w-0 -->
        <div class="brand-text-block" style="display: flex; flex-direction: column; justify-content: center; min-width: 0; max-width: 180px; line-height: 1.15;">
            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                <span class="text-truncate" title="{{ $displayName }}" style="font-family: 'Jost', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 15px; font-weight: 800; color: {{ $textColor }}; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    {{ $displayName }}
                </span>
                <span style="font-family: 'Jost', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 10px; font-weight: 700; color: #FFFFFF; background-color: #6D3FE0; padding: 2px 5px; border-radius: 6px; letter-spacing: 0.04em; text-transform: uppercase; line-height: 1.2; flex-shrink: 0;">
                    ISP
                </span>
            </div>
            @if($subtitleText)
                <span class="brand-network-name text-truncate" title="{{ $subtitleText }}" style="font-family: 'Jost', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; font-weight: 500; color: {{ $subtitleColor }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">
                    {{ $subtitleText }}
                </span>
            @endif
        </div>
    @endif
</div>
