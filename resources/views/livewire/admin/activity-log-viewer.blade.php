<div>
    <div class="ui-page-wrap">

        {{-- Header --}}
        <x-ui.page-header :title="__('System Activity Logs')" :subtitle="__('Monitor and audit system transactions, updates, and deletions')">
            <x-slot:actions>
                <button wire:click="resetFilters" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-repeat me-1"></i> {{ __('Reset Filters') }}
                </button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filter Bar --}}
        <div class="card">
            <div class="card-body py-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5 col-12">
                        <label class="form-label mb-1">{{ __('Search Keywords') }}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input wire:model.live.debounce.400ms="search" type="text" 
                                placeholder="{{ __('Search description, values, causer...') }}" 
                                class="form-control form-control-sm border-start-0">
                        </div>
                    </div>
                    <div class="col-md-3 col-12">
                        <label class="form-label mb-1">{{ __('Log Category') }}</label>
                        <select wire:model.live="logName" class="form-select form-select-sm">
                            <option value="all">{{ __('All Categories') }}</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}">{{ __(ucfirst($name)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label mb-1">{{ __('Event Type') }}</label>
                        <select wire:model.live="event" class="form-select form-select-sm">
                            <option value="all">{{ __('All Events') }}</option>
                            @foreach($events as $ev)
                                <option value="{{ $ev }}">{{ __(ucfirst($ev)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Log Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4 py-3">{{ __('Timestamp') }}</th>
                                <th>{{ __('Log Name') }}</th>
                                <th>{{ __('Event') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('User (Causer)') }}</th>
                                <th>{{ __('Subject') }}</th>
                                <th class="text-end pe-4">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="ps-4 small text-muted">
                                    <span class="text-dark fw-semibold d-block">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                    <span class="text-muted small">({{ $log->created_at->diffForHumans() }})</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        {{ __(ucfirst($log->log_name)) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $badgeColor = match($log->event) {
                                            'created' => 'success',
                                            'updated' => 'warning',
                                            'deleted' => 'danger',
                                            default => 'info'
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }}">
                                        {{ __(ucfirst($log->event ?: 'system')) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-dark" style="max-width: 350px; white-space: normal; line-height: 1.4;">
                                        {{ $log->description }}
                                    </div>
                                </td>
                                <td>
                                    @if($log->causer)
                                        <div class="fw-semibold text-dark">{{ $log->causer->name }}</div>
                                        <div class="text-muted small">{{ $log->causer->email }}</div>
                                    @else
                                        <span class="text-muted small">{{ __('System / API') }}</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @if($log->subject_type)
                                        <div class="fw-semibold text-dark">{{ class_basename($log->subject_type) }}</div>
                                        <div class="small">ID: {{ $log->subject_id }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button wire:click="showDetails({{ $log->id }})"
                                        class="btn btn-sm btn-secondary" title="{{ __('View Details') }}">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="p-0">
                                    <x-ui.empty-state icon="bi-clock-history" :title="__('No activity logs found matching the filters.')" :description="__('Try adjusting your search keywords, category, or event filter.')" />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                <div class="px-4 py-3 border-top">
                    {{ $logs->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Details Modal --}}
    @if($selectedLog)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(15, 23, 42, 0.4); z-index: 1050;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2 text-orange-500"></i>{{ __('Log Entry Details') }}
                    </h5>
                    <button wire:click="closeDetails" class="btn-close" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('Time') }}</span>
                            <span class="fw-semibold text-dark">{{ $selectedLog->created_at->format('d M Y, h:i:s A') }} ({{ $selectedLog->created_at->diffForHumans() }})</span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('Event') }}</span>
                            <span class="badge bg-secondary-subtle text-secondary">
                                {{ __(ucfirst($selectedLog->event ?: 'System')) }}
                            </span>
                        </div>
                        <div class="col-md-3">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('Log Category') }}</span>
                            <span class="badge bg-success-subtle text-success">
                                {{ __(ucfirst($selectedLog->log_name)) }}
                            </span>
                        </div>
                        <div class="col-12">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('Description') }}</span>
                            <span class="fw-semibold text-dark">{{ $selectedLog->description }}</span>
                        </div>
                        <div class="col-md-6">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('User / Causer') }}</span>
                            @if($selectedLog->causer)
                                <span class="fw-semibold text-dark">{{ $selectedLog->causer->name }}</span>
                                <span class="d-block text-muted small">{{ $selectedLog->causer->email }}</span>
                            @else
                                <span class="text-muted">{{ __('System / Background Worker') }}</span>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <span class="d-block small text-muted fw-semibold mb-1">{{ __('Subject Record') }}</span>
                            @if($selectedLog->subject_type)
                                <span class="fw-semibold text-dark">{{ $selectedLog->subject_type }}</span>
                                <span class="d-block text-muted small">Record ID: {{ $selectedLog->subject_id }}</span>
                            @else
                                <span class="text-muted">{{ __('None') }}</span>
                            @endif
                        </div>
                        
                        <div class="col-12">
                            <hr class="my-3 text-muted opacity-25">
                            <h6 class="ui-section-title mb-2">{{ __('Properties / Metadata') }}</h6>
                            <div class="bg-slate-50 p-3 rounded-xl font-monospace" style="max-height: 250px; overflow-y: auto;">
                                @if(!empty($selectedLog->properties) && count($selectedLog->properties) > 0)
                                    @if(isset($selectedLog->properties['attributes']) || isset($selectedLog->properties['old']))
                                        {{-- Updated/Created state changes --}}
                                        <div class="table-responsive">
                                            <table class="table table-sm bg-white text-start mb-0 align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Field') }}</th>
                                                        @if(isset($selectedLog->properties['old']))
                                                            <th>{{ __('Old Value') }}</th>
                                                        @endif
                                                        <th>{{ __('New/Current Value') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="small">
                                                    @php
                                                        $attributes = $selectedLog->properties['attributes'] ?? [];
                                                        $oldValues = $selectedLog->properties['old'] ?? [];
                                                        $allKeys = array_keys(array_merge($attributes, $oldValues));
                                                    @endphp
                                                    @foreach($allKeys as $key)
                                                        @if($key === 'password' || $key === 'token')
                                                            @continue
                                                        @endif
                                                        <tr>
                                                            <td class="fw-semibold text-muted">{{ $key }}</td>
                                                            @if(isset($selectedLog->properties['old']))
                                                                <td class="text-danger">{{ is_array($oldValues[$key] ?? '') ? json_encode($oldValues[$key]) : ($oldValues[$key] ?? '—') }}</td>
                                                            @endif
                                                            <td class="text-success">{{ is_array($attributes[$key] ?? '') ? json_encode($attributes[$key]) : ($attributes[$key] ?? '—') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        {{-- Generic custom values --}}
                                        <div class="table-responsive">
                                            <table class="table table-sm bg-white text-start mb-0 align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Property') }}</th>
                                                        <th>{{ __('Value') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="small">
                                                    @foreach($selectedLog->properties as $key => $val)
                                                        <tr>
                                                            <td class="fw-semibold text-muted">{{ $key }}</td>
                                                            <td class="text-dark fw-semibold">
                                                                @if(is_array($val))
                                                                    <pre class="mb-0 text-start" style="font-size:0.75rem;">{{ json_encode($val, JSON_PRETTY_PRINT) }}</pre>
                                                                @else
                                                                    {{ $val }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted small">{{ __('No property changes recorded.') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button wire:click="closeDetails" class="btn btn-sm btn-secondary">{{ __('Close Details') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
