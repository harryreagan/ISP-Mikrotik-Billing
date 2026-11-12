<div class="card mb-4">
    <div class="card-header py-3 px-4 position-relative">
        <h3 class="ui-section-title mb-0">
            {{ $title ?? '' }}
        </h3>
    </div>
    <div class="card-body p-4 {{ $class ?? '' }}" {{ $attributes ?? '' }}>
        {{ $aside ?? '' }}
    </div>
</div>