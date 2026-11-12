@props(['label' => '', 'error' => '', 'help' => '', 'required' => false])

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    @if($label)
        <label class="ui-form-label d-block mb-1">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if($error)
        <div class="ui-form-error mt-1">{{ $error }}</div>
    @elseif($help)
        <div class="ui-form-help mt-1">{{ $help }}</div>
    @endif
</div>

