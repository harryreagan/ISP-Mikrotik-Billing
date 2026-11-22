@props([
    'color' => 'auto',
    'height' => '38px',
    'showText' => true,
    'brandName' => null,
    'showSubtitle' => null,
    'asLink' => true,
    'href' => null,
    'showTenantBadge' => false,
])

@php
    $isLight = ($color === 'light');
    $logoSrc = $isLight 
        ? asset('images/hsmart-isp-logo-white.png') 
        : asset('images/hsmart-isp-logo.png');

    $targetUrl = $href ?: url('/');

    // White label / custom tenant: only if explicitly passed via brandName OR requested in tenant portal with showTenantBadge=true
    $customTenant = null;
    if ($brandName && !in_array(strtolower(trim((string)$brandName)), ['hsmart', 'hsmart platforms', 'hsmart isp', 'default'])) {
        $customTenant = $brandName;
    } elseif ($showTenantBadge && auth()->check() && !request()->is('terms*', 'privacy*', 'cookie*', 'landing*', 'login', 'register', 'forgot-password', 'password*')) {
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

        $genericDefaults = ['hsmart', 'hsmart isp', 'hsmart wifi', 'hsmart wifi billing', 'default isp', 'default tenant', 'hsmart platforms'];
        $candidates = array_filter([
            $rComp,
            $pBrand,
            $tName,
            $u->business_name ?? ($u->company ?? null),
        ], fn($val) => !empty($val) && !in_array(strtolower(trim((string) $val)), $genericDefaults));

        $found = reset($candidates) ?: null;
        if ($found) {
            $stripped = trim(preg_replace('/^hsmart\s+/i', '', (string) $found));
            $customTenant = $stripped !== '' ? $stripped : $found;
        }
    }
@endphp

@if($asLink)
<a href="{{ $targetUrl }}" {{ $attributes->merge(['class' => 'hsmart-brand-logo']) }} style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; user-select: none; transition: opacity 0.2s ease, transform 0.2s ease; outline: none;" aria-label="HSmart ISP">
    <img src="{{ $logoSrc }}" 
         alt="HSmart ISP - Smarter Internet. Always." 
         class="hsmart-logo-img" 
         style="height: {{ $height }}; width: auto; max-height: 100%; display: block; object-fit: contain; vertical-align: middle;" />

    @if(!empty($customTenant))
        <div style="display: inline-flex; align-items: center; gap: 6px; border-left: 1px solid {{ $isLight ? 'rgba(255,255,255,0.25)' : '#cbd5e1' }}; padding-left: 10px; margin-left: 2px;">
            <span style="font-family: 'Jost', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; font-weight: 700; color: {{ $isLight ? '#93c5fd' : '#1e40af' }}; background: {{ $isLight ? 'rgba(59,130,246,0.18)' : '#eff6ff' }}; padding: 2px 7px; border-radius: 6px; letter-spacing: 0.3px; text-transform: uppercase;">
                {{ $customTenant }}
            </span>
        </div>
    @endif
</a>
@else
<div {{ $attributes->merge(['class' => 'hsmart-brand-logo']) }} style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; user-select: none;">
    <img src="{{ $logoSrc }}" 
         alt="HSmart ISP - Smarter Internet. Always." 
         class="hsmart-logo-img" 
         style="height: {{ $height }}; width: auto; max-height: 100%; display: block; object-fit: contain; vertical-align: middle;" />

    @if(!empty($customTenant))
        <div style="display: inline-flex; align-items: center; gap: 6px; border-left: 1px solid {{ $isLight ? 'rgba(255,255,255,0.25)' : '#cbd5e1' }}; padding-left: 10px; margin-left: 2px;">
            <span style="font-family: 'Jost', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 11px; font-weight: 700; color: {{ $isLight ? '#93c5fd' : '#1e40af' }}; background: {{ $isLight ? 'rgba(59,130,246,0.18)' : '#eff6ff' }}; padding: 2px 7px; border-radius: 6px; letter-spacing: 0.3px; text-transform: uppercase;">
                {{ $customTenant }}
            </span>
        </div>
    @endif
</div>
@endif
