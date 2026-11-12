<div class="ui-page-wrap">
    <x-slot name="header">
        <h2 class="ui-page-title mb-0">
            {{ __('Packages Setup') }}
        </h2>
    </x-slot>

    <x-ui.page-header :title="__('Packages Setup')" :subtitle="__('Create and manage PPPoE and Hotspot packages and router profiles')" />

    <div class="row g-4">
        <!-- Form Panel -->
        <div class="col-lg-5 col-md-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <span class="ui-section-title">
                        <i class="bi bi-pencil-square me-2 text-orange-500"></i>{{ $package_id ? __('Edit Package') : __('Create Package') }}
                    </span>
                </x-slot>
                <x-slot name="aside">
                    @if(auth()->user()->can('package-setup-create') || $package_id)
                        <form wire:submit.prevent="createPackage">
                            <div class="row g-3">
                                <div class="col-md-6 col-12">
                                    <label class="form-label">{!! __('Plan Label :info', ['info' => '<span class="text-muted small">' . __('(e.g. MINOR)') . '</span>']) !!}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="plan_label" placeholder="{{ __('MINOR / JUNIOR / BASIC') }}">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">{!! __('Package Name :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="package_name" required>
                                    @error('package_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">{!! __('Speed :info', ['info' => '<span class="text-muted small">' . __('(e.g. 8 Mbps)') . '</span>']) !!}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="speed" placeholder="8 Mbps">
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">
                                        {{ __('MikroTik Rate Limit') }}
                                        <span class="text-muted small">{{ __('(e.g. 8M/8M)') }}</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-router"></i></span>
                                        <input type="text" class="form-control" wire:model.defer="mikrotik_rate_limit" placeholder="8M/8M or 512k/1M">
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">
                                        {!! __('Select Router :info', ['info' => '<span class="text-muted small">' . __('(Optional)') . '</span>']) !!}
                                    </label>
                                    <select class="form-select form-select-sm @error('router_name') is-invalid @enderror" wire:model.live="router_name">
                                        <option value="">-- {{ __('Apply to All Routers') }} --</option>
                                        @foreach($routers as $router)
                                            <option value="{{ $router->router_name }}">{{ $router->router_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('router_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">
                                        {{ __('Local Address') }}
                                        <span class="text-muted small">{{ __('(e.g. 192.168.1.1)') }}</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-geo"></i></span>
                                        <input type="text" list="pool-list" class="form-control" wire:model.defer="mikrotik_local_address" placeholder="{{ __('IP Address') }}">
                                    </div>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">
                                        {{ __('Remote Address/Pool') }}
                                        <span class="text-muted small">{{ __('(e.g. pool1)') }}</span>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text"><i class="bi bi-diagram-3"></i></span>
                                        <input type="text" list="pool-list" class="form-control" wire:model.defer="mikrotik_remote_address" placeholder="{{ __('Pool Name or IP') }}">
                                        <button class="btn btn-secondary" type="button" wire:click="loadPools" title="{{ __('Load Router Pools') }}">
                                            <i class="bi bi-arrow-clockwise" wire:loading.remove wire:target="loadPools"></i>
                                            <span class="spinner-border spinner-border-sm" wire:loading wire:target="loadPools"></span>
                                        </button>
                                    </div>
                                    <datalist id="pool-list">
                                        @foreach($mikrotik_pools as $pool)
                                            <option value="{{ $pool }}">{{ $pool }}</option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-md-6 col-12">
                                    <label class="form-label">{!! __('Monthly Price (KES ) :info', ['info' => '<span class="text-danger">*</span>']) !!}</label>
                                    <input type="number" class="form-control form-control-sm" wire:model.defer="price" required>
                                    @error('price') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{!! __('Features :info', ['info' => '<span class="text-muted small">' . __('(one per line)') . '</span>']) !!}</label>
                                    <textarea class="form-control form-control-sm" wire:model.defer="features_text" rows="4" placeholder="24 HOURS UNLIMITED&#10;Fiber Optics&#10;OTC Fee - 3000 Taka&#10;24/7 Customer Care"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{!! __('Description :info', ['info' => '<span class="text-muted small">' . __('(optional short note)') . '</span>']) !!}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="description">
                                </div>
                                
                                <div class="col-md-4 col-12">
                                    <label class="form-label">{{ __('Sort Order') }}</label>
                                    <input type="number" class="form-control form-control-sm" wire:model.defer="sort_order" min="0">
                                </div>
                                
                                <div class="col-md-8 col-12 d-flex flex-column justify-content-end gap-2 pt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" wire:model.defer="show_on_site" id="show_on_site">
                                        <label class="form-check-label text-muted small" for="show_on_site">{{ __('Show on Site') }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" wire:model.defer="is_featured" id="is_featured">
                                        <label class="form-check-label text-muted small" for="is_featured">{{ __('Featured Package') }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" wire:model.live="push_to_mikrotik" id="push_to_mikrotik">
                                        <label class="form-check-label text-muted small" for="push_to_mikrotik">
                                            <i class="bi bi-router text-orange-500 me-1"></i>{{ __('Push to MikroTik') }}
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12 mt-3 pt-2 border-top d-flex gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary px-4">
                                        <i class="bi bi-save me-1"></i>{{ __('Save') }}
                                    </button>
                                    @if($package_id)
                                        <button type="button" wire:click="$set('package_id', null)" class="btn btn-sm btn-secondary px-3">
                                            {{ __('Cancel') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-lock fs-2 d-block mb-2"></i>
                            {{ __("You don't have permission to create packages.") }}
                        </div>
                    @endif
                </x-slot>
            </x-mikrotik.section-form>
        </div>

        <!-- Package List Panel -->
        <div class="col-lg-7 col-md-12">
            <x-mikrotik.section-form>
                <x-slot name="title">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100">
                        <span class="ui-section-title"><i class="bi bi-list-task me-2 text-orange-500"></i>{{ __('Package List') }}</span>
                        <div class="d-flex gap-1">
                            <button wire:click="saveSortOrder" class="btn btn-sm btn-secondary">
                                <i class="bi bi-save me-1"></i>{{ __('Save Order') }}
                            </button>
                            <button wire:click="syncFromMikrotik"
                                    wire:loading.attr="disabled"
                                    class="btn btn-sm btn-primary">
                                <span wire:loading.remove wire:target="syncFromMikrotik">
                                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('Sync from MikroTik') }}
                                </span>
                                <span wire:loading wire:target="syncFromMikrotik">
                                    <span class="spinner-border spinner-border-sm me-1"></span>{{ __('Syncing...') }}
                                </span>
                            </button>
                        </div>
                    </div>
                </x-slot>
                <x-slot name="aside">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="py-2">#</th>
                                    <th class="py-2">{{ __('Label') }}</th>
                                    <th class="py-2">{{ __('Package') }}</th>
                                    <th class="py-2">{{ __('Router') }}</th>
                                    <th class="py-2">{{ __('Speed') }}</th>
                                    <th class="py-2">{{ __('Rate Limit') }}</th>
                                    <th class="py-2">{{ __('Price') }}</th>
                                    <th class="py-2 text-center">{{ __('On Site') }}</th>
                                    <th class="py-2 text-center"><i class="bi bi-router" title="Push Status"></i></th>
                                    <th class="py-2 text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody wire:sortable="updateSortOrder">
                                @forelse ($packagesData ?? [] as $package)
                                    <tr wire:sortable.item="{{ $package['id'] }}" wire:key="pkg-{{ $package['id'] }}" class="{{ $package['is_featured'] ? 'bg-orange-50' : '' }}">
                                        <td>
                                            <i wire:sortable.handle class="bi bi-grid-3x2-gap text-muted me-1" style="cursor: grab;"></i>
                                            {{ $package['sort_order'] }}
                                        </td>
                                        <td>
                                            @if($package['plan_label'])
                                                <span class="badge bg-success-subtle text-success">{{ $package['plan_label'] }}</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold text-dark">{{ $package['package'] }}</td>
                                        <td>
                                            @if($package['router'])
                                                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-router me-1"></i>{{ $package['router']['router_name'] }}</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">{{ __('All Routers') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $package['speed'] }}</td>
                                        <td>
                                            @if($package['mikrotik_rate_limit'])
                                                <code class="text-danger small">{{ $package['mikrotik_rate_limit'] }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold tabular-nums">{{ number_format($package['price'], 0) }}KES </td>
                                        <td class="text-center">
                                            @if($package['show_on_site'])
                                                <i class="bi bi-check-circle-fill text-success" title="{{ __('Visible') }}"></i>
                                            @else
                                                <i class="bi bi-x-circle-fill text-danger" title="{{ __('Hidden') }}"></i>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($package['push_to_mikrotik'])
                                                <i class="bi bi-check-circle-fill text-success" title="{{ __('Synced to MikroTik') }}"></i>
                                            @else
                                                <i class="bi bi-dash-circle text-muted" title="{{ __('Local Only') }}"></i>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-1">
                                                @can('package-setup-edit')
                                                    <button wire:click="editPackage({{ $package['id'] }})" class="btn btn-sm btn-secondary p-1 px-2" title="{{ __('Edit') }}">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                @endcan
                                                @can('package-setup-delete')
                                                    <button wire:click="deletePackage({{ $package['id'] }})"
                                                            wire:confirm="{{ __('Delete this package?') }}"
                                                            class="btn btn-sm btn-outline-danger p-1 px-2" title="{{ __('Delete') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-center text-muted py-4">{{ __('No packages yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-slot>
            </x-mikrotik.section-form>
        </div>
    </div>
</div>
