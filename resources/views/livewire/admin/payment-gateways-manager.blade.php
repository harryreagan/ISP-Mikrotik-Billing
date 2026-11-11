<div class="container-fluid py-4" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <style>
        .ch-card {
            cursor: pointer;
            border-radius: 14px;
            transition: all 0.2s ease;
            text-align: center;
            padding: 14px 10px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            position: relative;
        }
        .ch-card:hover {
            border-color: #CBD5E1;
            background: #F8FAFC;
        }
        .ch-card.selected-till {
            border: 2px solid #10B981 !important;
            background: #F0FDF4 !important;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.12);
        }
        .ch-card.selected-paybill {
            border: 2px solid #6366F1 !important;
            background: #EEF2FF !important;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.12);
        }
        .ch-card.selected-bank {
            border: 2px solid #8B5CF6 !important;
            background: #FAF5FF !important;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.12);
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
            background-color: #7C3AED;
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
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
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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
            background: #FAF5FF;
        }
        .bank-item:last-child {
            border-bottom: none;
        }
    </style>

    {{-- 1. Main Header --}}
    <div class="mb-4">
        <h1 class="h3 fw-bold mb-3" style="color: #0F172A; letter-spacing: -0.02em;">Online Gateways</h1>

        <!-- PayHero Integration Bar with Toggle -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #0F172A; font-size: 0.95rem;">PayHero M-Pesa Integration</h5>
                <p class="text-muted mb-0" style="font-size: 0.82rem;">
                    Accept M-Pesa STK Push payments routed directly to your Till, Paybill, or Bank account.
                </p>
            </div>
            <div>
                <button type="button" 
                        wire:click="togglePayheroEnabled" 
                        class="custom-switch-purple {{ $payhero_enabled ? 'active' : '' }}" 
                        title="{{ $payhero_enabled ? 'Disable PayHero STK Push' : 'Enable PayHero STK Push' }}">
                </button>
            </div>
        </div>

        <!-- STK Push Info Alert Banner -->
        <div class="p-3 mb-2" style="background: #FDFBFF; border: 1px solid #EDE9FE; border-radius: 12px;">
            <div class="fw-bold mb-1" style="color: #6D28D9; font-size: 0.82rem;">PayHero STK Push Enabled</div>
            <div style="color: #7C3AED; font-size: 0.78rem;">
                STK Push requests will automatically send to customer phones and settle into your registered store channel (Till, Paybill, or Bank account).
            </div>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs mb-4" role="alert" style="border-radius: 12px; background: #ECFDF5; color: #065F46; font-size: 0.85rem;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success"></i>
                <div class="fw-semibold">{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs mb-4" role="alert" style="border-radius: 12px; background: #FEF2F2; color: #991B1B; font-size: 0.85rem;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                <div class="fw-semibold">{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 2-Column Layout (Matching POS Image) --}}
    <div class="row g-4">
        {{-- Left Column: PayHero Settlement Channel Form --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-xs h-100 p-4" style="background: #FFFFFF; border: 1px solid #E2E8F0 !important; border-radius: 18px;">
                <!-- Card Header -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #F3E8FF; color: #7E22CE;">
                        <i class="bi bi-wallet2" style="font-size: 0.95rem;"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0" style="color: #0F172A; font-size: 0.92rem;">PayHero Settlement Channel</h6>
                        <span class="text-muted" style="font-size: 0.75rem;">Select whether to receive payments on your M-Pesa Till, Paybill, or Bank Account.</span>
                    </div>
                </div>

                <!-- SELECT CHANNEL TYPE Selector -->
                <div class="mb-3">
                    <label class="d-block fw-bold mb-2" style="font-size: 0.68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em;">
                        Select Channel Type
                    </label>
                    <div class="row g-2">
                        <!-- M-PESA TILL -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('buy_goods')" 
                                 class="ch-card {{ $channel_type === 'buy_goods' ? 'selected-till' : '' }}">
                                <div class="rounded-2 d-flex align-items-center justify-content-center mx-auto mb-2" 
                                     style="width: 34px; height: 34px; background: {{ $channel_type === 'buy_goods' ? '#10B981' : '#F1F5F9' }}; color: {{ $channel_type === 'buy_goods' ? '#FFFFFF' : '#94A3B8' }};">
                                    <i class="bi bi-shop" style="font-size: 1rem;"></i>
                                </div>
                                <div class="fw-bold" style="font-size: 0.72rem; color: {{ $channel_type === 'buy_goods' ? '#065F46' : '#1E293B' }};">M-PESA TILL</div>
                                <div style="font-size: 0.65rem; color: #94A3B8;">Buy Goods</div>
                            </div>
                        </div>

                        <!-- PAYBILL -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('paybill')" 
                                 class="ch-card {{ $channel_type === 'paybill' ? 'selected-paybill' : '' }}">
                                <div class="rounded-2 d-flex align-items-center justify-content-center mx-auto mb-2" 
                                     style="width: 34px; height: 34px; background: {{ $channel_type === 'paybill' ? '#6366F1' : '#F1F5F9' }}; color: {{ $channel_type === 'paybill' ? '#FFFFFF' : '#94A3B8' }};">
                                    <i class="bi bi-receipt" style="font-size: 1rem;"></i>
                                </div>
                                <div class="fw-bold" style="font-size: 0.72rem; color: {{ $channel_type === 'paybill' ? '#3730A3' : '#1E293B' }};">PAYBILL</div>
                                <div style="font-size: 0.65rem; color: #94A3B8;">Business No</div>
                            </div>
                        </div>

                        <!-- BANK -->
                        <div class="col-4">
                            <div wire:click="selectChannelType('bank')" 
                                 class="ch-card {{ $channel_type === 'bank' ? 'selected-bank' : '' }}">
                                <div class="rounded-2 d-flex align-items-center justify-content-center mx-auto mb-2" 
                                     style="width: 34px; height: 34px; background: {{ $channel_type === 'bank' ? '#8B5CF6' : '#F1F5F9' }}; color: {{ $channel_type === 'bank' ? '#FFFFFF' : '#94A3B8' }};">
                                    <i class="bi bi-bank" style="font-size: 1rem;"></i>
                                </div>
                                <div class="fw-bold" style="font-size: 0.72rem; color: {{ $channel_type === 'bank' ? '#5B21B6' : '#1E293B' }};">BANK</div>
                                <div style="font-size: 0.65rem; color: #94A3B8;">Direct Bank</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Inputs -->
                <form wire:submit.prevent="confirmAndLinkChannel">
                    <!-- 1. Till Number -->
                    @if($channel_type === 'buy_goods')
                        <div class="mb-3">
                            <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">
                                M-Pesa Till Number *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 10px 0 0 10px; border-color: #E2E8F0;">
                                    #
                                </span>
                                <input type="text" 
                                       wire:model.defer="till_number" 
                                       placeholder="e.g. 567812" 
                                       class="form-control border-start-0 fw-bold" 
                                       style="border-radius: 0 10px 10px 0; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.88rem; color: #1E293B;" />
                            </div>
                            @error('till_number') <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> @enderror
                            <div class="text-muted mt-1" style="font-size: 0.72rem;">Funds from STK Push will settle directly to this Buy Goods Till.</div>
                        </div>
                    @endif

                    <!-- 2. Paybill -->
                    @if($channel_type === 'paybill')
                        <div class="mb-3">
                            <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">
                                Paybill Business Number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="paybill_number" 
                                   placeholder="e.g. 247247" 
                                   class="form-control fw-bold" 
                                   style="border-radius: 10px; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.88rem; color: #1E293B;" />
                            @error('paybill_number') <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">
                                Account Ref / Number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="account_number" 
                                   placeholder="e.g. WIFI or SHOP101" 
                                   class="form-control fw-bold" 
                                   style="border-radius: 10px; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.88rem; color: #1E293B;" />
                            @error('account_number') <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <!-- 3. Bank Account -->
                    @if($channel_type === 'bank')
                        <div class="mb-3 position-relative">
                            <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">
                                Select Bank *
                            </label>
                            <div class="input-group">
                                <input type="text" 
                                       wire:model="bank_search" 
                                       wire:focus="$set('show_bank_dropdown', true)"
                                       placeholder="Search bank (e.g. Equity, KCB, Co-op, NCBA)..." 
                                       class="form-control fw-bold" 
                                       style="border-radius: 10px; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.85rem; color: #1E293B;" />
                                <button type="button" 
                                        wire:click="$toggle('show_bank_dropdown')" 
                                        class="btn btn-outline-secondary" 
                                        style="border-color: #E2E8F0; border-radius: 0 10px 10px 0;">
                                    <i class="bi bi-chevron-down small"></i>
                                </button>
                            </div>
                            @error('bank_name') <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> @enderror

                            <!-- Bank Dropdown overlay -->
                            @if($show_bank_dropdown)
                                <div class="bank-select-dropdown">
                                    @forelse($filteredBanks as $b)
                                        <div wire:click="selectBank('{{ $b['name'] }}', '{{ $b['code'] }}')" class="bank-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold" style="font-size: 0.8rem; color: #1E293B;">{{ $b['name'] }}</div>
                                                <span class="text-purple fw-bold" style="font-size: 0.68rem; color: #7C3AED;">Paybill Code: {{ $b['code'] }}</span>
                                            </div>
                                            <span class="badge" style="background: #F3E8FF; color: #7E22CE; font-size: 0.68rem;">Select</span>
                                        </div>
                                    @empty
                                        <div class="p-3 text-muted text-center" style="font-size: 0.78rem;">No bank found matching "{{ $bank_search }}"</div>
                                    @endforelse
                                </div>
                            @endif

                            @if($bank_name)
                                <div class="p-2 mt-2 d-flex justify-content-between align-items-center" style="background: #FAF5FF; border: 1px solid #E9D5FF; border-radius: 8px;">
                                    <span class="fw-bold text-dark" style="font-size: 0.75rem;">Selected: {{ $bank_name }}</span>
                                    <span class="badge" style="background: #E9D5FF; color: #5B21B6; font-size: 0.68rem;">Paybill: {{ $bank_code }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em;">
                                Bank Account Number *
                            </label>
                            <input type="text" 
                                   wire:model.defer="account_number" 
                                   placeholder="e.g. 012345678910" 
                                   class="form-control fw-bold" 
                                   style="border-radius: 10px; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.88rem; color: #1E293B;" />
                            @error('account_number') <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <!-- Friendly Label (Optional) -->
                    <div class="mb-4">
                        <label class="d-block fw-bold mb-1" style="font-size: 0.68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.05em;">
                            Friendly Label (Optional)
                        </label>
                        <input type="text" 
                               wire:model.defer="channel_name" 
                               placeholder="e.g. Main Counter Till, Equity Account" 
                               class="form-control" 
                               style="border-radius: 10px; border-color: #E2E8F0; background: #F8FAFC; font-size: 0.82rem; color: #334155;" />
                    </div>

                    <!-- Confirm Button (Black button matching POS) -->
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="btn w-100 fw-bold d-flex align-items-center justify-content-center gap-2" 
                            style="background: #0F172A; color: #FFFFFF; border-radius: 12px; padding: 12px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; border: none; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);">
                        <span wire:loading.remove wire:target="confirmAndLinkChannel">CONFIRM &amp; LINK PAYMENT CHANNEL</span>
                        <span wire:loading wire:target="confirmAndLinkChannel" class="d-inline-flex align-items-center gap-2">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span>Linking Channel...</span>
                        </span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Right Column: Active Settlement Channels List --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-xs h-100 p-4" style="background: #FFFFFF; border: 1px solid #E2E8F0 !important; border-radius: 18px;">
                <!-- Header with Title & Stats -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                    <div>
                        <div class="fw-bold" style="font-size: 0.68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em;">Active Settlement Channels</div>
                        <h6 class="fw-bold mb-0 mt-1 d-flex align-items-center gap-2" style="color: #0F172A; font-size: 0.95rem;">
                            <span>Payment Channels</span>
                            <span class="badge rounded-pill bg-light text-dark fw-bold px-2 py-1" style="font-size: 0.72rem;">{{ $channels->count() }}</span>
                        </h6>
                        <div class="text-muted" style="font-size: 0.75rem;">Channels used to collect hotspot and PPPoE subscription payments.</div>
                    </div>
                    <div>
                        <span class="badge px-3 py-1 rounded-pill" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.75rem; font-weight: 700;">
                            &bull; {{ $activeCount }} Active
                        </span>
                    </div>
                </div>

                <!-- Channels Table -->
                @if($channels->isEmpty())
                    <div class="text-center py-5 my-3" style="background: #F8FAFC; border: 2px dashed #E2E8F0; border-radius: 14px;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 52px; height: 52px; background: #EEF2FF; color: #6366F1;">
                            <i class="bi bi-credit-card-2-front" style="font-size: 1.5rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1" style="color: #0F172A; font-size: 0.9rem;">No settlement channels configured</h6>
                        <p class="text-muted mb-0 mx-auto" style="font-size: 0.78rem; max-width: 320px;">
                            Add an M-Pesa Buy Goods Till, Paybill, or Bank account using the form on the left to start collecting subscriber payments.
                        </p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                            <thead style="background: #F8FAFC; font-size: 0.68rem; color: #64748B; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #E2E8F0;">
                                <tr>
                                    <th class="py-2.5 ps-2">Type</th>
                                    <th class="py-2.5">Channel / Bank</th>
                                    <th class="py-2.5">Account / Identifier</th>
                                    <th class="py-2.5 text-center">Status</th>
                                    <th class="py-2.5 text-end pe-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($channels as $c)
                                    <tr style="border-bottom: 1px solid #F1F5F9; {{ !$c->is_active ? 'opacity: 0.65; background: #FAFBFD;' : '' }}">
                                        <!-- 1. Type Badge -->
                                        <td class="ps-2 py-3">
                                            @if($c->channel_type === 'bank')
                                                <span class="badge fw-bold" style="background: #FAF5FF; color: #7E22CE; border: 1px solid #E9D5FF; font-size: 0.68rem; padding: 4px 8px; border-radius: 6px;">
                                                    <i class="bi bi-bank me-1"></i> BANK ACCOUNT
                                                </span>
                                            @elseif(in_array($c->channel_type, ['till', 'buy_goods']))
                                                <span class="badge fw-bold" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.68rem; padding: 4px 8px; border-radius: 6px;">
                                                    <i class="bi bi-shop me-1"></i> TILL NUMBER
                                                </span>
                                            @else
                                                <span class="badge fw-bold" style="background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE; font-size: 0.68rem; padding: 4px 8px; border-radius: 6px;">
                                                    <i class="bi bi-receipt me-1"></i> PAYBILL
                                                </span>
                                            @endif
                                        </td>

                                        <!-- 2. Channel Name & Bank -->
                                        <td class="py-3">
                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <span class="fw-bold" style="color: #0F172A; font-size: 0.82rem;">
                                                    {{ $c->channel_name ?: 'Settlement Channel' }}
                                                </span>
                                                @if($c->is_default)
                                                    <span class="badge" style="background: #FEF3C7; color: #92400E; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 800;">
                                                        <i class="bi bi-star-fill me-1" style="color: #D97706;"></i> DEFAULT
                                                    </span>
                                                @endif
                                            </div>
                                            @if($c->bank_name)
                                                <div class="text-muted mt-0.5" style="font-size: 0.72rem;">
                                                    <i class="bi bi-building me-1"></i> {{ $c->bank_name }}
                                                </div>
                                            @endif
                                        </td>

                                        <!-- 3. Account / Identifier -->
                                        <td class="py-3">
                                            <span class="badge fw-bold" style="background: #F1F5F9; color: #334155; font-family: monospace; font-size: 0.75rem; padding: 4px 8px; border-radius: 6px;">
                                                @if(in_array($c->channel_type, ['till', 'buy_goods']))
                                                    <span style="color: #94A3B8; font-family: sans-serif; font-size: 0.65rem;">TILL:</span> {{ $c->short_code }}
                                                @elseif($c->channel_type === 'paybill')
                                                    <span style="color: #94A3B8; font-family: sans-serif; font-size: 0.65rem;">PAYBILL:</span> {{ $c->short_code }}
                                                    @if($c->account_number) <span style="color: #94A3B8; font-family: sans-serif; font-size: 0.65rem;">ACC:</span> {{ $c->account_number }} @endif
                                                @else
                                                    <span style="color: #94A3B8; font-family: sans-serif; font-size: 0.65rem;">ACC:</span> {{ $c->account_number }} ({{ $c->bank_code ?: $c->short_code }})
                                                @endif
                                            </span>
                                        </td>

                                        <!-- 4. Status Toggle Pill -->
                                        <td class="py-3 text-center">
                                            <div class="d-inline-flex flex-column align-items-center">
                                                <button type="button" 
                                                        wire:click="toggleChannelActive({{ $c->id }})" 
                                                        class="btn btn-sm py-1 px-2.5 rounded-pill fw-bold" 
                                                        style="{{ $c->is_active ? 'background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0;' : 'background: #F1F5F9; color: #64748B; border: 1px solid #CBD5E1;' }} font-size: 0.72rem;">
                                                    <span class="d-inline-block rounded-circle me-1" style="width: 6px; height: 6px; background: {{ $c->is_active ? '#10B981' : '#94A3B8' }};"></span>
                                                    {{ $c->is_active ? 'Active' : 'Inactive' }}
                                                </button>
                                                <span class="text-muted mt-0.5" style="font-size: 0.65rem;">
                                                    {{ $c->is_active ? 'Click to disable' : 'Click to enable' }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 5. Actions: Make Default & Delete -->
                                        <td class="py-3 text-end pe-2">
                                            <div class="d-inline-flex align-items-center gap-1.5">
                                                @if(!$c->is_default && $c->is_active)
                                                    <button type="button" 
                                                            wire:click="setDefaultChannel({{ $c->id }})" 
                                                            class="btn btn-sm btn-light border fw-semibold" 
                                                            style="font-size: 0.7rem; border-radius: 8px; color: #334155; padding: 4px 8px;"
                                                            title="Make this the default collection channel">
                                                        <i class="bi bi-star text-warning me-1"></i> Make Default
                                                    </button>
                                                @endif

                                                <button type="button" 
                                                        wire:click="deleteChannel({{ $c->id }})" 
                                                        wire:confirm="Are you sure you want to delete channel '{{ $c->channel_name }}'?"
                                                        class="btn btn-sm fw-bold" 
                                                        style="background: #FFF1F2; color: #E11D48; border: 1px solid #FECDD3; border-radius: 8px; font-size: 0.7rem; padding: 4px 8px;"
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

                <!-- Table Footer Helper Note (matching POS image) -->
                <div class="mt-auto pt-3 border-top d-flex align-items-center" style="font-size: 0.72rem; color: #64748B;">
                    <span>💡 <strong>Tip:</strong> Mark unused channels as <strong>Inactive</strong> to hide them from customer payment portals without losing historical transaction records.</span>
                </div>
            </div>
        </div>
    </div>
</div>
