<x-app-layout>
    <x-slot name="header">
        <h2 class="h4 font-weight-bold text-dark mb-0">
            <i class="bi bi-person-plus-fill me-2 text-orange-500"></i>{{ __('Create Reseller') }}
        </h2>
    </x-slot>

    <x-ui.page-header
        :title="__('Create new reseller')"
        :subtitle="__('Configure login credentials, commission rules, packages, and portal permissions')"
    >
        <x-slot:actions>
            <a href="{{ route('admin.resellers.index') }}" wire:navigate class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('Back to Resellers') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="ui-section-title mb-0"><i class="bi bi-person-plus-fill text-orange-500 me-2"></i>{{ __('Create New Reseller') }}</h5>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form action="{{ route('admin.resellers.store') }}" method="POST">
                @csrf
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6 mb-4">
                        <h6 class="ui-section-title mb-3 border-bottom pb-2"><i class="bi bi-shield-lock me-1 text-orange-500"></i>{{ __('Login Credentials') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label">{{ __('Full Name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. John Doe">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Email Address') }}</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required placeholder="e.g. john@example.com">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Password') }}</label>
                                <input type="password" name="password" class="form-control" required placeholder="Minimum 8 characters">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Confirm Password') }}</label>
                                <input type="password" name="password_confirmation" class="form-control" required placeholder="Retype password">
                            </div>
                        </div>
                    </div>

                    <!-- Reseller Configuration -->
                    <div class="col-md-6 mb-4">
                        <h6 class="ui-section-title mb-3 border-bottom pb-2"><i class="bi bi-sliders me-1 text-orange-500"></i>{{ __('Reseller Configuration') }}</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Company Name (Optional)') }}</label>
                                <input type="text" name="company" class="form-control" value="{{ old('company') }}" placeholder="e.g. Acme ISP Ltd">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Mobile Number') }}</label>
                                <div wire:ignore>
                                    <input type="text" name="mobile" class="form-control" x-data="intlTelInput()" value="{{ old('mobile') }}" required placeholder="e.g. 0712XXXXXX">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Commission Percentage (%)') }}</label>
                                <input type="number" step="0.1" name="commission_percentage" class="form-control" value="{{ old('commission_percentage', '10.0') }}" required min="0" max="100" placeholder="e.g. 15">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>{{ __('Suspended') }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 mt-2">
                            <label class="ui-section-title d-block border-bottom pb-1 mb-2">{{ __('Voucher & Billing Rules') }}</label>
                            
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="allow_fixed_voucher" value="0">
                                <input class="form-check-input" type="checkbox" name="allow_fixed_voucher" value="1" id="allow_fixed_voucher" {{ old('allow_fixed_voucher', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label text-dark fw-medium text-xs" for="allow_fixed_voucher">{{ __('Allow Custom/Fixed Amount Vouchers') }}</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="allow_partial_activation" value="0">
                                <input class="form-check-input" type="checkbox" name="allow_partial_activation" value="1" id="allow_partial_activation" {{ old('allow_partial_activation', '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label text-dark fw-medium text-xs" for="allow_partial_activation">{{ __('Allow Active Connection on Partial Payment') }}</label>
                            </div>
                            
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="use_prorated_validity" value="0">
                                <input class="form-check-input" type="checkbox" name="use_prorated_validity" value="1" id="use_prorated_validity" {{ old('use_prorated_validity', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label text-dark fw-medium text-xs" for="use_prorated_validity">{{ __('Enable Prorated Daily Validity (Fallback)') }}</label>
                            </div>

                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="pay_discounted_upfront" value="0">
                                <input class="form-check-input" type="checkbox" name="pay_discounted_upfront" value="1" id="pay_discounted_upfront" {{ old('pay_discounted_upfront', '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label text-dark fw-medium text-xs" for="pay_discounted_upfront">{{ __('Pay Discounted Price Upfront (Deduct commission upfront)') }}</label>
                            </div>
                        </div>
                    </div>

                    <!-- Assign Admin Packages -->
                    <div class="col-12 mb-4">
                        <h6 class="ui-section-title mb-1 border-bottom pb-2"><i class="bi bi-box-seam me-1 text-orange-500"></i>{{ __('Assign Admin Packages') }}</h6>
                        <p class="text-muted small mb-3">{{ __('Select which master billing packages this reseller is authorized to resell to their customers.') }}</p>
                        
                        <div class="row g-2">
                            @forelse($packages as $package)
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check bg-slate-50 p-3 rounded-xl ring-1 ring-slate-200/70 h-100">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" name="packages[]" value="{{ $package->id }}" id="packageCheck{{ $package->id }}" {{ is_array(old('packages')) && in_array($package->id, old('packages')) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold text-dark cursor-pointer d-block" for="packageCheck{{ $package->id }}">
                                            {{ $package->package }}
                                            <span class="d-block text-muted small fw-normal">Price: KES {{ number_format($package->price, 2) }} | Router: {{ $package->router_name ?? 'Global' }}</span>
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center text-muted py-2">{{ __('No admin packages available. Create master packages first.') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Reseller Permissions -->
                    <div class="col-12 mb-4">
                        <h6 class="ui-section-title mb-1 border-bottom pb-2">
                            <i class="bi bi-shield-lock-fill text-orange-500 me-2"></i>{{ __('Reseller Permissions') }}
                        </h6>
                        <p class="text-muted small mb-3">{{ __('Select which features this reseller can access from their portal. Leave all unchecked to grant no special access.') }}</p>

                        <div class="row g-3">
                            @foreach($resellerPermissions as $groupName => $permissions)
                                <div class="col-md-6 col-xl-4">
                                    <div class="card bg-slate-50 h-100">
                                        <div class="card-header">
                                            <span class="ui-section-title">
                                                @if($groupName === 'Customer Management')
                                                    <i class="bi bi-people me-1 text-orange-500"></i>
                                                @elseif($groupName === 'Customer Status Control')
                                                    <i class="bi bi-toggle2-on me-1 text-orange-500"></i>
                                                @elseif($groupName === 'Billing & Payments')
                                                    <i class="bi bi-cash-coin me-1 text-orange-500"></i>
                                                @elseif($groupName === 'Reports & Lists')
                                                    <i class="bi bi-bar-chart-line me-1 text-orange-500"></i>
                                                @elseif($groupName === 'Setup & Access')
                                                    <i class="bi bi-gear-fill me-1 text-orange-500"></i>
                                                @else
                                                    <i class="bi bi-box2 me-1 text-orange-500"></i>
                                                @endif
                                                {{ $groupName }}
                                            </span>
                                        </div>
                                        <div class="card-body pt-2 px-3">
                                            @foreach($permissions as $permName => $permLabel)
                                                <div class="form-check mb-1">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permName }}"
                                                        id="perm_create_{{ $permName }}"
                                                        {{ in_array($permName, old('permissions', $defaultPermissions)) ? 'checked' : '' }}>
                                                    <label class="form-check-label small text-dark" for="perm_create_{{ $permName }}">
                                                        {{ $permLabel }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('[name=\'permissions[]\']').forEach(cb => cb.checked = true)">
                                <i class="bi bi-check-all me-1"></i>{{ __('Select All') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('[name=\'permissions[]\']').forEach(cb => cb.checked = false)">
                                <i class="bi bi-x-circle me-1"></i>{{ __('Clear All') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('admin.resellers.index') }}" wire:navigate class="btn btn-sm btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>{{ __('Save Reseller') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
