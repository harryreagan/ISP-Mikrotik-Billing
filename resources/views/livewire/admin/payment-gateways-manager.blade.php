<div>
    <style>
        .ch-card {
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.2s ease;
            text-align: center;
            padding: 14px 10px;
            border: 1px solid rgba(226, 232, 240, 0.7);
            background: #FFFFFF;
            position: relative;
            color: #1e293b;
        }
        .ch-card:hover {
            border-color: #f97316;
            background: #F8FAFC;
        }
        .ch-card.selected-till,
        .ch-card.selected-paybill,
        .ch-card.selected-bank {
            border: 2px solid #f97316 !important;
            background: #fff7ed !important;
        }

        /* Dark Theme Support for Channel Selector Cards */
        html.dark .ch-card,
        [data-bs-theme="dark"] .ch-card {
            background: #1e293b !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #f8fafc !important;
        }
        html.dark .ch-card:hover,
        [data-bs-theme="dark"] .ch-card:hover {
            background: #334155 !important;
            border-color: #f97316 !important;
        }
        html.dark .ch-card.selected-till,
        html.dark .ch-card.selected-paybill,
        html.dark .ch-card.selected-bank,
        [data-bs-theme="dark"] .ch-card.selected-till,
        [data-bs-theme="dark"] .ch-card.selected-paybill,
        [data-bs-theme="dark"] .ch-card.selected-bank {
            border: 2px solid #f97316 !important;
            background: rgba(249, 115, 22, 0.18) !important;
        }
        html.dark .ch-card .text-slate-800,
        [data-bs-theme="dark"] .ch-card .text-slate-800,
        html.dark .ch-card-title,
        [data-bs-theme="dark"] .ch-card-title {
            color: #f8fafc !important;
        }
        html.dark .ch-card .text-slate-500,
        [data-bs-theme="dark"] .ch-card .text-slate-500,
        html.dark .ch-card-sub,
        [data-bs-theme="dark"] .ch-card-sub {
            color: #94a3b8 !important;
        }
        html.dark .ch-card .bg-slate-100,
        [data-bs-theme="dark"] .ch-card .bg-slate-100 {
            background-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        /* Dark Theme Support for Left/Right Content Cards & Inputs */
        html.dark .card,
        [data-bs-theme="dark"] .card {
            background-color: var(--surface, #171b2e) !important;
            border-color: var(--border-ring, rgba(51, 65, 85, 0.6)) !important;
            color: var(--text-primary, #f1f5f9) !important;
        }
        html.dark .form-control,
        [data-bs-theme="dark"] .form-control {
            background-color: #0f172a !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
            color: #f8fafc !important;
        }
        html.dark .form-control:focus,
        [data-bs-theme="dark"] .form-control:focus {
            background-color: #0f172a !important;
            border-color: #f97316 !important;
            color: #f8fafc !important;
            box-shadow: 0 0 0 0.2rem rgba(249, 115, 22, 0.25) !important;
        }

        /* Loading indicator visibility rule */
        [wire\:loading] {
            display: none !important;
        }
        button[disabled] [wire\:loading] {
            display: inline-flex !important;
        }

        .custom-switch-purple {
            width: 48px;
            height: 26px;
            background-color: #E2E8F0;
            border-radius: 20px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.25s ease;
            display: inline-block;
            border: none;
        }
        .custom-switch-purple.active {
            background-color: #f97316;
        }
        .custom-switch-purple::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            top: 3px;
            left: 3px;
            transition: transform 0.25s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .custom-switch-purple.active::after {
            transform: translateX(22px);
        }
        .bank-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 240px;
            overflow-y: auto;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            z-index: 50;
            margin-top: 4px;
        }
        .bank-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #F1F5F9;
            transition: background 0.15s ease;
        }
        .bank-item:hover {
            background: #F8FAFC;
        }
        .bank-item:last-child {
            border-bottom: none;
        }

        html.dark .bank-select-dropdown,
        [data-bs-theme="dark"] .bank-select-dropdown {
            background: #1e293b !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4) !important;
        }
        html.dark .bank-item,
        [data-bs-theme="dark"] .bank-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #f1f5f9 !important;
        }
        html.dark .bank-item:hover,
        [data-bs-theme="dark"] .bank-item:hover {
            background: #334155 !important;
        }
        html.dark .alert-info,
        [data-bs-theme="dark"] .alert-info {
            background-color: rgba(14, 165, 233, 0.12) !important;
            border-color: rgba(14, 165, 233, 0.3) !important;
            color: #7dd3fc !important;
        }
    </style>

    {{-- 1. Main Header --}}
    <x-ui.page-header
        title="Online gateways"
        subtitle="Accept M-Pesa STK Push payments routed directly to your Till, Paybill, or Bank account."
    >
        <x-slot:actions>
            <div class="d-flex align-items-center gap-2">
                <span class="text-xs font-medium text-slate-600">PayHero M-Pesa Integration</span>
                <button type="button" 
                        wire:click="togglePayheroEnabled" 
                        class="custom-switch-purple {{ $payhero_enabled ? 'active' : '' }}" 
                        title="{{ $payhero_enabled ? 'Disable PayHero STK Push' : 'Enable PayHero STK Push' }}">
                </button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4">
        <!-- STK Push Info Alert Banner -->
        <div class="alert alert-info mb-0">
            <div class="fw-semibold mb-0.5 text-xs">PayHero STK Push Enabled</div>
            <div class="text-xs text-slate-600">
                STK Push requests will automatically send to customer phones and settle into your registered store channel (Till, Paybill, or Bank account).
            </div>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success"></i>
                <div class="fw-semibold">{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                <div class="fw-semibold">{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 2-Column Layout --}}
    <div class="row g-4">
        {{-- Left Column: PayHero Settlement Channel Form --}}
        <div class="col-12 col-lg-5">
            <div class="card h-100 p-4">
                <!-- Card Header -->
                <div class="d-flex align-items-center gap-2.5 mb-4">
                    <div class="rounded-xl d-flex align-items-center justify-content-center bg-orange-50 text-orange-600" style="width: 36px; height: 36px;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <h6 class="ui-section-title mb-0">PayHero Settlement Channel</h6>
                        <span class="text-muted text-xs">Select whether to receive payments on your M-Pesa Till, Paybill, or Bank Account.</span>
                    </div>
                </div>

                <!-- SELECT CHANNEL TYPE Selector -->
                <div class="mb-3">
                    <label class="form-label mb-2">
                        Select channel type
                    </label>
                    <div class="row g-2">
                        <!-- M-PESA TILL -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('buy_goods')" 
                                 class="ch-card {{ $channel_type === 'buy_goods' ? 'selected-till' : '' }}">
                                <div class="rounded-lg d-flex align-items-center justify-content-center mx-auto mb-2 {{ $channel_type === 'buy_goods' ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-500' }}" 
                                     style="width: 34px; height: 34px;">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div class="text-xs font-semibold text-slate-800">M-Pesa Till</div>
                                <div class="text-[11px] text-slate-500">Buy Goods</div>
                            </div>
                        </div>

                        <!-- PAYBILL -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('paybill')" 
                                 class="ch-card {{ $channel_type === 'paybill' ? 'selected-paybill' : '' }}">
                                <div class="rounded-lg d-flex align-items-center justify-content-center mx-auto mb-2 {{ $channel_type === 'paybill' ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-500' }}" 
                                     style="width: 34px; height: 34px;">
                                    <i class="bi bi-receipt"></i>
                                </div>
                                <div class="text-xs font-semibold text-slate-800">Paybill</div>
                                <div class="text-[11px] text-slate-500">Business No</div>
                            </div>
                        </div>

                        <!-- BANK -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('bank')" 
                                 class="ch-card {{ $channel_type === 'bank' ? 'selected-bank' : '' }}">
                                <div class="rounded-lg d-flex align-items-center justify-content-center mx-auto mb-2 {{ $channel_type === 'bank' ? 'bg-orange-500 text-white' : 'bg-slate-100 text-slate-500' }}" 
                                     style="width: 34px; height: 34px;">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div class="text-xs font-semibold text-slate-800">Bank</div>
                                <div class="text-[11px] text-slate-500">Direct Bank</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Inputs -->
                <form wire:submit.prevent="confirmAndLinkChannel">
                    <!-- 1. Till Number -->
                    @if($channel_type === 'buy_goods')
                        <div class="mb-3">
                            <label class="form-label">
                                M-Pesa Till number *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">#</span>
                                <input type="text" 
                                       wire:model.defer="till_number" 
                                       placeholder="e.g. 567812" 
                                       class="form-control fw-semibold" />
                            </div>
                            @error('till_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            <div class="form-text">Funds from STK Push will settle directly to this Buy Goods Till.</div>
                        </div>
                    @endif

                    <!-- 2. Paybill -->
                    @if($channel_type === 'paybill')
                        <div class="mb-3">
                            <label class="form-label">
                                Paybill business number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="paybill_number" 
                                   placeholder="e.g. 247247" 
                                   class="form-control fw-semibold" />
                            @error('paybill_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Account ref / number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="account_number" 
                                   placeholder="e.g. WIFI or SHOP101" 
                                   class="form-control fw-semibold" />
                            @error('account_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <!-- 3. Bank Account -->
                    @if($channel_type === 'bank')
                        <div class="mb-3 position-relative">
                            <label class="form-label">
                                Select bank *
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       wire:model="bank_search" 
                                       wire:focus="$set('show_bank_dropdown', true)"
                                       placeholder="Search bank (e.g. Equity, KCB, Co-op, NCBA)..." 
                                       class="form-control fw-semibold" />
                                <button type="button" 
                                        wire:click="$toggle('show_bank_dropdown')" 
                                        class="btn btn-secondary">
                                    <i class="bi bi-chevron-down small"></i>
                                </button>
                            </div>
                            @error('bank_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                            <!-- Bank Dropdown overlay -->
                            @if($show_bank_dropdown)
                                <div class="bank-select-dropdown">
                                    @forelse($filteredBanks as $b)
                                        <div wire:click="selectBank('{{ $b['name'] }}', '{{ $b['code'] }}')" class="bank-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold text-xs text-slate-800">{{ $b['name'] }}</div>
                                                <span class="text-orange-600 text-[11px] font-medium">Paybill Code: {{ $b['code'] }}</span>
                                            </div>
                                            <span class="badge bg-primary">Select</span>
                                        </div>
                                    @empty
                                        <div class="p-3 text-muted text-center text-xs">No bank found matching "{{ $bank_search }}"</div>
                                    @endforelse
                                </div>
                            @endif

                            @if($bank_name)
                                <div class="p-2 mt-2 d-flex justify-content-between align-items-center bg-orange-50 rounded-lg">
                                    <span class="fw-semibold text-slate-800 text-xs">Selected: {{ $bank_name }}</span>
                                    <span class="badge bg-primary">Paybill: {{ $bank_code }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Bank account number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="account_number" 
                                   placeholder="e.g. 012345678910" 
                                   class="form-control fw-semibold" />
                            @error('account_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <!-- Friendly Label (Optional) -->
                    <div class="mb-4">
                        <label class="form-label">
                            Friendly label (optional)
                        </label>
                        <input type="text" 
                               wire:model.defer="channel_name" 
                               placeholder="e.g. Main Counter Till, Equity Account" 
                               class="form-control" />
                    </div>

                    <!-- Confirm Button -->
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            wire:target="confirmAndLinkChannel"
                            class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 py-2.5 font-semibold">
                        <span wire:loading.remove wire:target="confirmAndLinkChannel">Confirm &amp; link payment channel</span>
                        <span wire:loading wire:target="confirmAndLinkChannel" style="display: none;">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            <span>Linking Channel...</span>
                        </span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Right Column: Active Settlement Channels List --}}
        <div class="col-12 col-lg-7">
            <div class="card h-100 p-4">
                <!-- Header with Title & Stats -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <div class="text-xs text-slate-500">Active settlement channels</div>
                        <h6 class="ui-section-title mb-0 mt-0.5 d-flex align-items-center gap-2">
                            <span>Payment Channels</span>
                            <span class="badge rounded-pill bg-secondary">{{ $channels->count() }}</span>
                        </h6>
                        <div class="text-muted text-xs">Channels used to collect hotspot and PPPoE subscription payments.</div>
                    </div>
                    <div>
                        <span class="badge bg-success">
                            &bull; {{ $activeCount }} Active
                        </span>
                    </div>
                </div>

                <!-- Channels Table -->
                @if($channels->isEmpty())
                    <x-ui.empty-state
                        icon="bi-credit-card-2-front"
                        title="No settlement channels configured"
                        description="Add an M-Pesa Buy Goods Till, Paybill, or Bank account using the form on the left to start collecting subscriber payments."
                    />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Type</th>
                                    <th>Channel / Bank</th>
                                    <th>Account / Identifier</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($channels as $c)
                                    <tr style="{{ !$c->is_active ? 'opacity: 0.65;' : '' }}">
                                        <!-- 1. Type Badge -->
                                        <td class="ps-3 py-3">
                                            @if($c->channel_type === 'bank')
                                                <span class="badge bg-info">
                                                    <i class="bi bi-bank me-1"></i> Bank account
                                                </span>
                                            @elseif(in_array($c->channel_type, ['till', 'buy_goods']))
                                                <span class="badge bg-success">
                                                    <i class="bi bi-shop me-1"></i> Till number
                                                </span>
                                            @else
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-receipt me-1"></i> Paybill
                                                </span>
                                            @endif
                                        </td>

                                        <!-- 2. Channel Name & Bank -->
                                        <td class="py-3">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="fw-semibold text-slate-800 text-sm">
                                                    {{ $c->channel_name ?: 'Settlement Channel' }}
                                                </span>
                                                @if($c->is_default)
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-star-fill me-1"></i> Default
                                                    </span>
                                                @endif
                                            </div>
                                            @if($c->bank_name)
                                                <div class="text-muted text-xs mt-0.5">
                                                    <i class="bi bi-building me-1"></i> {{ $c->bank_name }}
                                                </div>
                                            @endif
                                        </td>

                                        <!-- 3. Account / Identifier -->
                                        <td class="py-3">
                                            <span class="badge bg-secondary font-monospace">
                                                @if(in_array($c->channel_type, ['till', 'buy_goods']))
                                                    <span class="text-slate-400 font-sans">Till:</span> {{ $c->short_code }}
                                                @elseif($c->channel_type === 'paybill')
                                                    <span class="text-slate-400 font-sans">Paybill:</span> {{ $c->short_code }}
                                                    @if($c->account_number) <span class="text-slate-400 font-sans">Acc:</span> {{ $c->account_number }} @endif
                                                @else
                                                    <span class="text-slate-400 font-sans">Acc:</span> {{ $c->account_number }} ({{ $c->bank_code ?: $c->short_code }})
                                                @endif
                                            </span>
                                        </td>

                                        <!-- 4. Status Toggle Pill -->
                                        <td class="py-3 text-center">
                                            <div class="d-inline-flex flex-column align-items-center">
                                                <button type="button" 
                                                        wire:click="toggleChannelActive({{ $c->id }})" 
                                                        class="btn btn-xs {{ $c->is_active ? 'btn-outline-success' : 'btn-secondary' }} rounded-pill">
                                                    <span class="d-inline-block rounded-circle me-1" style="width: 6px; height: 6px; background: {{ $c->is_active ? '#10B981' : '#94A3B8' }};"></span>
                                                    {{ $c->is_active ? 'Active' : 'Inactive' }}
                                                </button>
                                                <span class="text-muted mt-0.5 text-[11px]">
                                                    {{ $c->is_active ? 'Click to disable' : 'Click to enable' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 5. Actions: Make Default & Delete -->
                                        <td class="py-3 text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-1.5">
                                                @if(!$c->is_default && $c->is_active)
                                                    <button type="button" 
                                                            wire:click="setDefaultChannel({{ $c->id }})" 
                                                            class="btn btn-xs btn-secondary" 
                                                            title="Make this the default collection channel">
                                                        <i class="bi bi-star text-warning me-1"></i> Make Default
                                                    </button>
                                                @endif

                                                <button type="button" 
                                                        wire:click="deleteChannel({{ $c->id }})" 
                                                        wire:confirm="Are you sure you want to delete channel '{{ $c->channel_name }}'?"
                                                        class="btn btn-xs btn-outline-danger" 
                                                        title="Permanently remove channel">
                                                    <i class="bi bi-trash3 me-1"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <!-- Table Footer Helper Note -->
                <div class="mt-auto pt-3 border-top d-flex align-items-center text-xs text-slate-500">
                    <span>💡 <strong>Tip:</strong> Mark unused channels as <strong>Inactive</strong> to hide them from customer payment portals without losing historical transaction records.</span>
                </div>
            </div>
        </div>
    </div>
</div>
