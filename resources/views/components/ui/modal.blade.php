@props(['show' => false, 'title' => '', 'maxWidth' => 'modal-lg'])

@if($show)
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(1px);" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered {{ $maxWidth }}">
        <div class="modal-content ui-card border-0 p-1">
            @if($title || isset($header))
                <div class="modal-header border-0 pb-2 px-4 pt-4">
                    <h5 class="ui-section-title mb-0">{{ $title }}</h5>
                    @if(isset($close))
                        {{ $close }}
                    @endif
                </div>
            @endif
            <div class="modal-body px-4 py-3">
                {{ $slot }}
            </div>
            @if(isset($footer))
                <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
@endif

