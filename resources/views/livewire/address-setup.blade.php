<div class="ui-page-wrap">
    <x-slot name="header">
        <h2 class="ui-page-title mb-0">
            {{ __('Address Setup') }}
        </h2>
    </x-slot>

    <x-ui.page-header :title="__('Address Setup')" :subtitle="__('Define custom customer address fields, dropdown options, and receipt layout order')" />

    <div class="row g-4">
        <!-- Edit / Create Form Panel -->
        <div class="col-lg-4 col-md-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <span class="ui-section-title">
                        <i class="bi bi-pencil-square me-2 text-orange-500"></i>{{ $addressFieldId ? __('Edit Field') : __('Create Field') }}
                    </span>
                </x-slot>
                <x-slot name="aside">
                    @if(auth()->user()->can('address-setup-create') || $addressFieldId)
                        <form wire:submit.prevent="submit" x-data="{ input_type: @entangle('input_type') }">
                            <div class="mb-3">
                                <x-mikrotik.form-group
                                    column="col-12"
                                    label="{{ __('Label Name') }}"
                                    name="label"
                                    type="text"
                                    placeholder="{{ __('Enter label name (eg. Area, Road)') }}"
                                />
                            </div>

                            <div class="mb-3 bg-slate-50 p-3 rounded-xl border">
                                <div class="form-check mb-2">
                                    <input type="checkbox" id="required" class="form-check-input" wire:model="required">
                                    <label for="required" class="form-check-label fw-semibold text-dark">
                                        {{ __('Required Field') }} <span class="text-danger">*</span>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" id="print_preview" class="form-check-input" wire:model="print_preview">
                                    <label for="print_preview" class="form-check-label text-muted small">
                                        {{ __('Preview in receipt') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" id="complain_preview" class="form-check-input" wire:model="complain_preview">
                                    <label for="complain_preview" class="form-check-label text-muted small">
                                        {{ __('Preview in Complain List') }}
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3 bg-slate-50 p-3 rounded-xl border">
                                <x-mikrotik.form-group
                                    x-model="input_type"
                                    column="col-12 mb-2"
                                    label="{{ __('Field Input Type') }}"
                                    type="dropdownKey"
                                    name="input_type"
                                    placeholder="{{ __('Select Type') }}"
                                    :options="['dropdown' => __('Dropdown / List'), 'text' => __('Input Text (Single Line)'), 'textarea' => __('Input Box (Multi Line)')]"
                                />

                                <!-- Dropdown options manager -->
                                <div class="mt-3" x-show="input_type === 'dropdown'" x-cloak>
                                    <label for="dropdown_input" class="form-label mb-1">
                                        {{ __('List Options') }}
                                        <small class="text-muted fw-normal ms-1">({{ __('Edit updates on Save') }})</small>
                                    </label>
                                    <div class="input-group input-group-sm mb-2">
                                        <input type="text" wire:model.defer="dropdown_input" wire:keydown.enter.prevent="addTypeToList" id="dropdown_input" class="form-control" placeholder="{{ __('Add option item (eg. Dhanmondi)') }}">
                                        <button type="button" class="btn btn-primary" wire:click="addTypeToList">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                    <x-input-error for='dropdown_input' />

                                    <!-- Added options list -->
                                    @if (!empty($dropdown_list))
                                        <div class="d-flex flex-column gap-1 mt-2" style="max-height: 250px; overflow-y: auto;">
                                            @foreach ($dropdown_list as $index => $type)
                                                @php
                                                    $origName = array_search($type, $renamedOptions);
                                                    $isRenamed = ($origName !== false);
                                                @endphp

                                                @if ($editingOptionIndex === $index)
                                                    <!-- Inline Option Edit Input -->
                                                    <div class="d-flex align-items-center gap-1 p-1 bg-white border border-warning rounded-lg w-100 my-1" wire:key="opt-edit-{{ $index }}">
                                                        <input type="text"
                                                               wire:model.defer="editingOptionValue"
                                                               wire:keydown.enter.prevent="saveEditOption"
                                                               wire:keydown.escape="cancelEditOption"
                                                               class="form-control form-control-sm border-0 py-1"
                                                               placeholder="{{ __('Correct spelling...') }}"
                                                               autofocus>
                                                        <button type="button" class="btn btn-sm btn-primary px-2 py-1" wire:click="saveEditOption" title="{{ __('Confirm new name in list') }}">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-secondary px-2 py-1" wire:click="cancelEditOption" title="{{ __('Cancel') }}">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <!-- Option Item Card -->
                                                    <div class="d-flex align-items-center justify-content-between p-1.5 px-2.5 rounded-lg border bg-white" wire:key="opt-item-{{ $index }}-{{ $type }}">
                                                        <div class="d-flex align-items-center gap-2 text-truncate me-2">
                                                            <i class="bi bi-geo-alt-fill text-orange-500 small"></i>
                                                            <span class="fw-semibold text-dark text-truncate small">{{ $type }}</span>

                                                            @if ($isRenamed)
                                                                <span class="badge bg-warning-subtle text-warning" title="{{ __('Previous name:') }} {{ $origName }}">
                                                                    <i class="bi bi-clock-history me-1"></i>{{ __('Renamed (Pending Save)') }}
                                                                </span>
                                                            @elseif ($addressFieldId)
                                                                @php $countInUse = $this->countTypeInUse($type); @endphp
                                                                @if ($countInUse > 0)
                                                                    <span class="badge bg-success-subtle text-success" title="{{ $countInUse }} {{ __('customers currently use this address') }}">
                                                                        <i class="bi bi-people me-1"></i>{{ $countInUse }}
                                                                    </span>
                                                                @endif
                                                            @endif
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                            <button type="button" class="btn btn-sm btn-secondary p-1" wire:click="startEditOption({{ $index }})" title="{{ __('Edit spelling') }}">
                                                                <i class="bi bi-pencil-fill"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger p-1" wire:click="removeTypeFromList({{ $index }})" title="{{ __('Delete or Migrate') }}">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    <!-- Staged Merges Notice -->
                                    @if (!empty($mergedOptions))
                                        <div class="mt-2">
                                            @foreach ($mergedOptions as $fromOpt => $toOpt)
                                                <div class="alert alert-warning py-1 px-2 small mb-1 d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <i class="bi bi-arrow-right-short me-1"></i><strong>"{{ $fromOpt }}"</strong> {{ __('will merge into') }} <strong>"{{ $toOpt }}"</strong>
                                                    </span>
                                                    <span class="badge bg-warning-subtle text-warning">{{ __('Pending Save') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <x-input-error for='dropdown_list' />
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                @if($addressFieldId)
                                    <button type="button" class="btn btn-sm btn-secondary px-3" wire:click="cancelEdit">
                                        {{ __('Cancel') }}
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-sm btn-primary px-4">
                                    <i class="bi bi-save me-1"></i>{{ __('Save') }}
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-lock fs-2 d-block mb-2"></i>
                            {{ __('You do not have permission to manage Address Setup.') }}
                        </div>
                    @endif
                </x-slot>
            </x-mikrotik.section-form>
        </div>

        <!-- Address Fields List & Sort Panel -->
        <div class="col-lg-5 col-md-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <span class="ui-section-title"><i class="bi bi-list-stars me-2 text-orange-500"></i>{{ __('Address Fields & Preview') }}</span>
                </x-slot>
                <x-slot name="aside">
                    <div class="p-1">
                        @if (!empty($addressFields))
                            <ul wire:sortable="updateSortOrderAddress" id="sortable-list" class="list-group mb-3 gap-2">
                                @foreach ($addressFields as $field)
                                    <li wire:sortable.item="{{ $field['id'] }}" wire:key="field-{{ $field['id'] }}" class="list-group-item border rounded-xl p-3 bg-slate-50">
                                        <div class="row align-items-center g-2">
                                            <!-- Field Drag Handle & Label -->
                                            <div class="col-md-5 d-flex align-items-center gap-2">
                                                <i wire:sortable.handle class="bi bi-grid-3x2-gap text-muted" style="cursor: move;"></i>
                                                <span class="fw-semibold text-dark">{{ $field['label'] }}</span>
                                                @if ($field['required'] == 1)
                                                    <span class="text-danger">*</span>
                                                @endif
                                            </div>
                                            <!-- Preview element -->
                                            <div class="col-md-4">
                                                @if ($field['input_type'] == 'dropdown')
                                                    <select class="form-select form-select-sm" disabled>
                                                        @foreach ((json_decode($field['dropdown_list'], true) ?: []) as $type)
                                                            <option value="{{ $type }}">{{ $type }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" class="form-control form-control-sm" placeholder="{{ __($field['input_type']) }}" disabled>
                                                @endif
                                            </div>
                                            <!-- Action controls -->
                                            <div class="col-md-3 text-end d-flex justify-content-end gap-1">
                                                <button type="button" class="btn btn-sm btn-secondary p-1 px-2" wire:click="openBatchModal('{{ $field['label'] }}')" title="{{ __('Fix / Replace in Customers') }}">
                                                    <i class="bi bi-tools"></i>
                                                </button>

                                                @can('address-setup-edit')
                                                    <button type="button" class="btn btn-sm btn-secondary p-1 px-2" wire:click="edit({{ $field['id'] }})" title="{{ __('Edit') }}">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                @endcan

                                                @can('address-setup-delete')
                                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" wire:click="delete({{ $field['id'] }})" title="{{ __('Delete') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @endcan

                                                @if ($field['print_preview'] == 1)
                                                    <span class="badge bg-success-subtle text-success d-inline-flex align-items-center p-1" title="{{ __('Prints in receipt') }}">
                                                        <i class="bi bi-printer"></i>
                                                    </span>
                                                @endif
                                                @if ($field['complain_preview'] == 1)
                                                    <span class="badge bg-info-subtle text-info d-inline-flex align-items-center p-1" title="{{ __('Shows in complain') }}">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <!-- Save Button -->
                            <button type="button" class="btn btn-sm btn-primary px-4" wire:click="saveSortOrderAddress">
                                <i class="bi bi-save me-1"></i>{{ __('Save Fields Order') }}
                            </button>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-geo fs-2 d-block mb-2"></i>
                                {{ __('No address fields available. Create one to begin.') }}
                            </div>
                        @endif
                    </div>
                </x-slot>
            </x-mikrotik.section-form>
        </div>

        <!-- Receipt Layout Sorting Panel -->
        <div class="col-lg-3 col-md-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <span class="ui-section-title"><i class="bi bi-receipt me-2 text-orange-500"></i>{{ __('Receipt Section') }}</span>
                </x-slot>
                <x-slot name="aside">
                    <div class="p-1">
                        <div class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Drag fields to order how they print on receipt:') }}</div>
                        @if (!empty($receiptOrders))
                            <div wire:sortable="updateSortOrderReceipt" class="d-flex flex-column gap-2 mb-3">
                                @foreach ($receiptOrders as $field)
                                    <div wire:sortable.item="{{ $field['id'] }}" wire:key="field-{{ $field['id'] }}" class="d-flex align-items-center gap-2 border rounded-xl p-2 bg-slate-50" style="cursor: move;">
                                        <i wire:sortable.handle class="bi bi-grid-3x2-gap text-muted"></i>
                                        <span class="small fw-semibold text-dark">{{ $field['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <!-- Save Button -->
                            <button type="button" class="btn btn-sm btn-primary w-100" wire:click="saveSortOrderReceipt">
                                <i class="bi bi-save me-1"></i>{{ __('Save Receipt Order') }}
                            </button>
                        @else
                            <p class="text-muted text-center py-3 small">{{ __('No receipt address fields available.') }}</p>
                        @endif
                    </div>
                </x-slot>
            </x-mikrotik.section-form>
        </div>
    </div>

    <!-- Merge & Delete Option Modal -->
    @if ($mergeModalOpen)
        <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(15, 23, 42, 0.4);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle text-warning me-2"></i>{{ __('Option In Use: Migrate Customers') }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeMergeModal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            {{ __('The option') }} <strong>"{{ $mergeFromOption }}"</strong> {{ __('is currently assigned to') }} <strong class="badge bg-dark">{{ $mergeCustomerCount }}</strong> {{ __('customer(s).') }}
                        </div>
                        <p class="small text-muted mb-3">
                            {{ __('To delete this option without losing customer addresses, select an existing option to migrate these customers to (will apply when you click Save):') }}
                        </p>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Reassign customers to:') }}</label>
                            <select wire:model.defer="mergeToOption" class="form-select form-select-sm">
                                <option value="">-- {{ __('Select Destination Option') }} --</option>
                                @foreach ($dropdown_list as $opt)
                                    @if ($opt !== $mergeFromOption)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="closeMergeModal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary px-3" wire:click="confirmMergeAndDelete">
                            <i class="bi bi-arrow-repeat me-1"></i>{{ __('Confirm Migration') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Batch Replace / Spellcheck Customer Address Modal -->
    @if ($batchModalOpen)
        <div class="modal fade show" tabindex="-1" style="display: block; background: rgba(15, 23, 42, 0.4);" aria-modal="true" role="dialog">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-tools me-2 text-orange-500"></i>{{ __('Fix / Replace Customer Address') }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeBatchModal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="small text-muted mb-3">
                            {{ __('Find any misspelled address and replace it with the correct spelling across all customers simultaneously.') }}
                        </p>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Select Address Field') }}</label>
                            <select wire:model.live="batchFieldLabel" class="form-select form-select-sm">
                                <option value="">-- {{ __('Select Field') }} --</option>
                                @foreach ($addressFields as $f)
                                    <option value="{{ $f['label'] }}">{{ $f['label'] }} ({{ ucfirst($f['input_type']) }})</option>
                                @endforeach
                            </select>
                            <x-input-error for="batchFieldLabel" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Current / Misspelled Value') }}</label>
                            <input type="text" wire:model.live.debounce.300ms="batchOldValue" class="form-control form-control-sm" placeholder="{{ __('e.g. Dahnmondi') }}">
                            <x-input-error for="batchOldValue" />

                            @if (!is_null($batchMatchCount))
                                <div class="mt-2 small">
                                    @if ($batchMatchCount > 0)
                                        <div class="alert alert-success py-1 px-2 mb-0">
                                            <i class="bi bi-check-circle-fill me-1"></i><strong>{{ $batchMatchCount }}</strong> {{ __('customer(s) found with this exact address.') }}
                                        </div>
                                    @else
                                        <div class="alert alert-secondary py-1 px-2 mb-0 text-muted">
                                            <i class="bi bi-info-circle me-1"></i>{{ __('No customers currently have this address.') }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('New / Correct Spelling') }}</label>
                            <input type="text" wire:model.defer="batchNewValue" class="form-control form-control-sm" placeholder="{{ __('e.g. Dhanmondi') }}">
                            <x-input-error for="batchNewValue" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="closeBatchModal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-sm btn-primary px-4" wire:click="executeBatchReplace">
                            <i class="bi bi-check2-circle me-1"></i>{{ __('Update All Customers') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
