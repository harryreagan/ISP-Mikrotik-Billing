@props(['title' => '', 'subtitle' => '', 'padded' => true])

<div {{ $attributes->merge(['class' => 'ui-card card']) }}>
    @if($title || $subtitle || isset($actions))
        <div class="ui-card-header card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                @if($title)
                    <h2 class="ui-section-title mb-0">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="ui-page-subtitle mb-0 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($actions))
                <div class="d-flex align-items-center gap-2">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif
    <div class="{{ $padded ? 'card-body p-4' : 'card-body p-0' }}">
        {{ $slot }}
    </div>
</div>

