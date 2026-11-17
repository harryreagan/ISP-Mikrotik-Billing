@props(['title' => '', 'subtitle' => ''])

<div {{ $attributes->merge(['class' => 'd-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-3 mt-2 mt-sm-3 mb-4']) }}>
    <div>
        <h1 class="ui-page-title mb-0">{{ $title }}</h1>
        @if($subtitle)
            <p class="ui-page-subtitle mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="d-flex flex-wrap align-items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>

