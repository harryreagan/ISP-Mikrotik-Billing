@props(['title' => '', 'description' => '', 'icon' => 'bi-inbox'])

<div {{ $attributes->merge(['class' => 'd-flex flex-column align-items-center justify-content-center text-center py-5 px-3']) }}>
    <div class="ui-empty-icon mb-3">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h3 class="ui-section-title mb-1">{{ $title }}</h3>
    @if($description)
        <p class="ui-page-subtitle mb-0" style="max-width: 360px;">{{ $description }}</p>
    @endif
    @if(isset($actions) || !$slot->isEmpty())
        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
            {{ $actions ?? '' }}
            {{ $slot }}
        </div>
    @endif
</div>

