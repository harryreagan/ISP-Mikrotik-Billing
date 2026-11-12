@props(['title' => '', 'subtitle' => ''])

<div {{ $attributes->merge(['class' => 'ui-card card overflow-hidden']) }}>
    @if($title || $subtitle || isset($actions) || isset($filters))
        <div class="ui-card-header card-header">
            @if($title || $subtitle || isset($actions))
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 {{ isset($filters) ? 'mb-3' : '' }}">
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
            @if(isset($filters))
                <div class="d-flex flex-wrap align-items-center gap-2">
                    {{ $filters }}
                </div>
            @endif
        </div>
    @endif
    <div class="table-responsive">
        {{ $slot }}
    </div>
    @if(isset($footer))
        <div class="card-footer bg-transparent border-top px-4 py-3">
            {{ $footer }}
        </div>
    @endif
</div>

