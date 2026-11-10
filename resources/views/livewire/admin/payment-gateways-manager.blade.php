<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size: 0.8rem;">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Dashboard</a></li>
                    <li class="breadcrumb-item active text-primary" aria-current="page">Payment Gateways</li>
                </ol>
            </nav>
            <h2 class="h3 fw-bold mb-1" style="color: #0F172A;">M-Pesa & Payment Gateways</h2>
            <p class="text-muted mb-0" style="font-size: 0.88rem;">
                Configure automated M-Pesa STK Push, Till, Paybill, and Card processing for your ISP subscribers.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button wire:click="openCreateModal('payhero')" class="btn btn-warning text-white fw-bold shadow-sm d-inline-flex align-items-center gap-2" style="border-radius: 10px; background: #F47B06; border-color: #F47B06;">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add M-Pesa Channel</span>
            </button>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px; background: #ECFDF5; color: #065F46;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                <div class="fw-semibold">{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 12px; background: #FEF2F2; color: #991B1B;">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                <div class="fw-semibold">{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Gateway Providers Overview Cards --}}
    <div class="row g-3 mb-4">
        <!-- PayHero M-Pesa Hub -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px; background: linear-gradient(145deg, #ffffff, #fafafa); border: 1px solid #E2E8F0 !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width: 44px; height: 44px; background: #00A651;">
                            <i class="bi bi-phone-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: #0F172A;">PayHero Kenya</h5>
                            <span class="badge" style="background: #E0F2FE; color: #0369A1; font-size: 0.7rem;">Recommended</span>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-success px-3 py-2">Instant STK</span>
                </div>
                <p class="text-muted small mb-3">
                    Direct integration for Till (Buy Goods), Paybill, and Bank channels with zero Safaricom Daraja paperwork.
                </p>
                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">{{ $channels->where('gateway', 'payhero')->count() }} configured</span>
                    <button wire:click="openCreateModal('payhero')" class="btn btn-sm btn-outline-success fw-bold">Configure</button>
                </div>
            </div>
        </div>

        <!-- Daraja M-Pesa Native -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px; background: linear-gradient(145deg, #ffffff, #fafafa); border: 1px solid #E2E8F0 !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width: 44px; height: 44px; background: #15803D;">
                            <i class="bi bi-shield-lock-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: #0F172A;">Safaricom Daraja</h5>
                            <span class="badge" style="background: #F1F5F9; color: #475569; font-size: 0.7rem;">Native API</span>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Direct Safaricom C2B / Lipa Na M-Pesa Online credentials for your own dedicated Paybill or Till.
                </p>
                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">{{ $channels->where('gateway', 'daraja')->count() }} configured</span>
                    <button wire:click="openCreateModal('daraja')" class="btn btn-sm btn-outline-secondary fw-bold">Configure</button>
                </div>
            </div>
        </div>

        <!-- KopoKopo -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 p-4" style="border-radius: 16px; background: linear-gradient(145deg, #ffffff, #fafafa); border: 1px solid #E2E8F0 !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width: 44px; height: 44px; background: #F59E0B;">
                            <i class="bi bi-wallet2 fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0" style="color: #0F172A;">KopoKopo</h5>
                            <span class="badge" style="background: #FEF3C7; color: #B45309; font-size: 0.7rem;">Till / Merchant</span>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Accept payments into your KopoKopo merchant account with automated webhooks and transaction verification.
                </p>
                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">{{ $channels->where('gateway', 'kopokopo')->count() }} configured</span>
                    <button wire:click="openCreateModal('kopokopo')" class="btn btn-sm btn-outline-warning fw-bold">Configure</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Channels Table --}}
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0" style="color: #0F172A;">Your Active Payment Channels</h5>
            <span class="badge bg-light text-dark fw-bold px-3 py-2">{{ $channels->count() }} Registered</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B;">
                    <tr>
                        <th class="ps-4">Channel & Provider</th>
                        <th>Type</th>
                        <th>Shortcode / Account</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th>Last Verified</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($channels as $c)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 32px; height: 32px; background: {{ $c->gateway === 'payhero' ? '#00A651' : ($c->gateway === 'daraja' ? '#15803D' : '#F59E0B') }};">
                                        <i class="bi {{ $c->channel_type === 'bank' ? 'bi-bank' : ($c->channel_type === 'paybill' ? 'bi-receipt' : 'bi-shop') }} small"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $c->channel_name }}</div>
                                        <div class="text-muted" style="font-size: 0.78rem;">Provider: {{ strtoupper($c->gateway) }} @if($c->payhero_channel_id)&bull; ID: {{ $c->payhero_channel_id }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 0.75rem; text-transform: uppercase;">
                                    {{ $c->channel_type }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark font-monospace">{{ $c->short_code ?: 'N/A' }}</span>
                                @if($c->account_number)
                                    <div class="text-muted small">Acc: {{ $c->account_number }}</div>
                                @endif
                            </td>
                            <td>
                                @if($c->is_active)
                                    <span class="badge" style="background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0;">
                                        <i class="bi bi-check-circle-fill me-1" style="font-size: 7px;"></i> Active
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($c->is_default)
                                    <span class="badge" style="background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;">
                                        ★ Default
                                    </span>
                                @else
                                    <button wire:click="setDefaultChannel({{ $c->id }})" class="btn btn-sm btn-link text-muted p-0 text-decoration-none small">
                                        Set as default
                                    </button>
                                @endif
                            </td>
                            <td>
                                <span class="text-muted small">
                                    {{ $c->last_tested_at ? \Carbon\Carbon::parse($c->last_tested_at)->diffForHumans() : 'Never tested' }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button wire:click="testChannelConnection({{ $c->id }})" class="btn btn-outline-secondary" title="Test Connection">
                                        <i class="bi bi-arrow-repeat {{ $isTesting ? 'spin' : '' }}"></i> Test
                                    </button>
                                    <button wire:click="editChannel({{ $c->id }})" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button wire:click="deleteChannel({{ $c->id }})" wire:confirm="Remove this payment channel?" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="bi bi-wallet2 fs-1 text-secondary"></i></div>
                                <h6 class="fw-bold mb-1">No payment channels registered yet</h6>
                                <p class="text-muted small mb-3">Connect your PayHero Till or Daraja Paybill to start accepting automated payments.</p>
                                <button wire:click="openCreateModal('payhero')" class="btn btn-warning text-white fw-bold shadow-sm" style="border-radius: 8px; background: #F47B06; border-color: #F47B06;">
                                    Connect PayHero Till
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Channel Modal --}}
    @if($showChannelModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                    <div class="modal-header border-0 pb-0 px-4 pt-4">
                        <div>
                            <h4 class="modal-title fw-bold" style="color: #0F172A;">
                                {{ $editingChannelId ? 'Edit Payment Channel' : 'Register Payment Channel' }}
                            </h4>
                            <p class="text-muted small mb-0">Configure your M-Pesa collection credentials for automated subscriber top-ups.</p>
                        </div>
                        <button type="button" wire:click="$set('showChannelModal', false)" class="btn-close" aria-label="Close"></button>
                    </div>

                    <form wire:submit.prevent="saveChannel">
                        <div class="modal-body px-4 py-3">
                            <div class="row g-3">
                                <!-- Gateway Selector -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold small">Payment Gateway Provider</label>
                                    <select wire:model.live="gateway" class="form-select">
                                        <option value="payhero">PayHero Kenya (Recommended)</option>
                                        <option value="daraja">Safaricom Daraja API</option>
                                        <option value="kopokopo">KopoKopo API</option>
                                    </select>
                                </div>

                                <!-- Channel Type -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold small">Channel Type</label>
                                    <select wire:model.live="channel_type" class="form-select">
                                        <option value="till">Buy Goods Till Number</option>
                                        <option value="paybill">Paybill & Account Number</option>
                                        <option value="bank">Bank Account</option>
                                    </select>
                                </div>

                                <!-- Channel Name -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Channel Name / Label</label>
                                    <input type="text" wire:model="channel_name" class="form-control" placeholder="e.g. Town Hotspot Till 542199">
                                    @error('channel_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <!-- Till / Paybill Shortcode -->
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold small">{{ $channel_type === 'till' ? 'Till Number' : ($channel_type === 'paybill' ? 'Paybill Number' : 'Account Identifier') }}</label>
                                    <input type="text" wire:model="short_code" class="form-control" placeholder="e.g. 849201">
                                    @error('short_code') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <!-- Account Number (for Paybill/Bank) -->
                                @if($channel_type !== 'till')
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold small">Account Number (Default)</label>
                                        <input type="text" wire:model="account_number" class="form-control" placeholder="e.g. WIFI or Leave blank for Customer Phone">
                                        @error('account_number') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <!-- PayHero Channel ID -->
                                @if($gateway === 'payhero')
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold small">PayHero Channel ID</label>
                                        <input type="text" wire:model="payhero_channel_id" class="form-control" placeholder="Found in PayHero Channels tab (e.g. 348)">
                                        @error('payhero_channel_id') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                @endif

                                <!-- API Token / Consumer Key -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">{{ $gateway === 'payhero' ? 'PayHero API Token (Bearer)' : ($gateway === 'daraja' ? 'Daraja Consumer Key' : 'Client ID') }}</label>
                                    <input type="password" wire:model="api_key" class="form-control font-monospace" placeholder="{{ $editingChannelId ? '•••••••• (Leave blank to keep existing)' : 'Paste API Token' }}">
                                    @error('api_key') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <!-- API Secret / Passkey -->
                                @if($gateway !== 'payhero')
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold small">{{ $gateway === 'daraja' ? 'Consumer Secret' : 'Client Secret' }}</label>
                                        <input type="password" wire:model="api_secret" class="form-control font-monospace" placeholder="Paste Secret">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold small">{{ $gateway === 'daraja' ? 'Daraja Passkey' : 'Webhook Secret' }}</label>
                                        <input type="password" wire:model="passkey" class="form-control font-monospace" placeholder="Paste Passkey">
                                    </div>
                                @endif

                                <!-- Flags -->
                                <div class="col-12">
                                    <div class="d-flex align-items-center gap-4 pt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="is_default" id="switchDefault">
                                            <label class="form-check-label fw-semibold small" for="switchDefault">Set as primary collection channel</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="is_active" id="switchActive">
                                            <label class="form-check-label fw-semibold small" for="switchActive">Active & Enabled</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 px-4 pb-4 pt-0">
                            <button type="button" wire:click="$set('showChannelModal', false)" class="btn btn-light fw-bold" style="border-radius: 10px;">Cancel</button>
                            <button type="submit" class="btn btn-warning text-white fw-bold px-4 shadow-sm" style="border-radius: 10px; background: #F47B06; border-color: #F47B06;">
                                {{ $editingChannelId ? 'Save Changes' : 'Register Channel' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

