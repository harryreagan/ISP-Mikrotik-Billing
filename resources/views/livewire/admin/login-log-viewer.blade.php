<div>
    <div class="ui-page-wrap">

        {{-- Header --}}
        <x-ui.page-header :title="__('User Authentication Logs')" :subtitle="__('Monitor and audit logins and logouts for both administrators and customers')">
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
                    <div class="col-md-6">
                        <label class="form-label mb-1">{{ __('Search Username/IP') }}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input wire:model.live.debounce.400ms="search" type="text" 
                                placeholder="{{ __('Search by username, IP address, user agent...') }}" 
                                class="form-control form-control-sm border-start-0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ __('Action Type') }}</label>
                        <select wire:model.live="action" class="form-select form-select-sm">
                            <option value="all">{{ __('All Actions') }}</option>
                            <option value="login">{{ __('Login Only') }}</option>
                            <option value="logout">{{ __('Logout Only') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">{{ __('User Type') }}</label>
                        <select wire:model.live="userType" class="form-select form-select-sm">
                            <option value="all">{{ __('All Users') }}</option>
                            <option value="admin">{{ __('Administrators') }}</option>
                            <option value="reseller">{{ __('Resellers') }}</option>
                            <option value="customer">{{ __('Customers') }}</option>
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
                                <th>{{ __('User (Username/Email)') }}</th>
                                <th>{{ __('User Type') }}</th>
                                <th>{{ __('Action') }}</th>
                                <th>{{ __('IP Address') }}</th>
                                <th class="pe-4">{{ __('User Agent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr wire:key="login-log-{{ $log->id }}">
                                <td class="ps-4 small text-muted">
                                    {{ $log->created_at->format('d M Y, h:i A') }}
                                    <div class="small text-muted">({{ $log->created_at->diffForHumans() }})</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $log->username }}</div>
                                </td>
                                <td>
                                    @if($log->authenticatable_type === \App\Models\User::class)
                                        @if($log->authenticatable?->reseller)
                                            <span class="badge bg-warning-subtle text-warning">
                                                {{ __('Reseller') }}
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">
                                                {{ __('Admin') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge bg-info-subtle text-info">
                                            {{ __('Customer') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->action === 'login')
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Login') }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <i class="bi bi-box-arrow-left me-1"></i> {{ __('Logout') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="small font-monospace text-secondary">
                                    {{ $log->ip_address ?? 'N/A' }}
                                </td>
                                <td class="pe-4 py-3 small text-muted text-truncate" style="max-width: 250px;" title="{{ $log->user_agent }}">
                                    {{ $log->user_agent }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="p-0">
                                    <x-ui.empty-state icon="bi-shield-lock" :title="__('No authentication logs found.')" :description="__('Adjust your search or user type filters.')" />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($logs->hasPages())
                <div class="card-footer border-0 py-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
