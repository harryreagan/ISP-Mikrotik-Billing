<div>
    <div class="container-fluid py-4 zoom-in">

        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-0" style="color:#1a1f36;">
                    <i class="bi bi-credit-card-2-front me-2 text-success"></i>{{ __('Kenyan Payment Gateways') }}
                </h4>
                <p class="text-muted small mb-0">{{ __('Configure M-Pesa SMS Forwarder, Daraja STK/C2B, PayHero, and KopoKopo integrations') }}</p>
            </div>
            <div>
                <button wire:click="save" class="btn btn-sm btn-success rounded-3 px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check-circle me-1"></i> {{ __('Save Gateway Settings') }}
                </button>
            </div>
        </div>

        {{-- Flash message --}}
        @if ($statusMessage)
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                <i class="bi bi-shield-check me-2"></i> {{ $statusMessage }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4">
            {{-- 1. M-Pesa SMS Forwarder --}}
            <div class="col-lg-6 col-12">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success-subtle text-success p-2 rounded-circle me-2">
                                <i class="bi bi-chat-dots-fill fs-6"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-0">{{ __('M-Pesa SMS Forwarder') }}</h6>
                                <small class="text-muted">{{ __('Android SMS Forwarder webhook for Paybill / Till / Send Money') }}</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-5 mb-0">
                            <input class="form-check-input" type="checkbox" wire:model.live="payment_mpesa_sms_enabled" id="smsSwitch">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Authorization Bearer Token') }}</label>
                            <input type="text" wire:model="sms_receiver_token" class="form-control form-control-sm" placeholder="e.g. mpesa-forwarder-secret-key-2026">
                            <small class="text-muted">{{ __('Must match the Bearer token configured in the Android SMS Forwarder app.') }}</small>
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <label class="form-label small fw-bold text-dark mb-1">{{ __('Webhook Endpoint URL') }}</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" readonly value="{{ url('/api/payment/mfs/sms-receiver') }}">
                                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('{{ url('/api/payment/mfs/sms-receiver') }}')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="bi bi-shield-lock me-1"></i> {{ __('Requires Header: Authorization: Bearer <token>. Sender must be MPESA.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Safaricom Daraja --}}
            <div class="col-lg-6 col-12">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-success-subtle text-success p-2 rounded-circle me-2">
                                <i class="bi bi-phone-fill fs-6"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-0">{{ __('M-Pesa Daraja (Safaricom)') }}</h6>
                                <small class="text-muted">{{ __('Direct Lipa Na M-Pesa STK Push and C2B Paybill callbacks') }}</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-5 mb-0">
                            <input class="form-check-input" type="checkbox" wire:model.live="payment_daraja_enabled" id="darajaSwitch">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Environment') }}</label>
                                <select wire:model="daraja_env" class="form-select form-select-sm">
                                    <option value="sandbox">{{ __('Sandbox') }}</option>
                                    <option value="production">{{ __('Production (Live)') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Paybill / Shortcode') }}</label>
                                <input type="text" wire:model="daraja_shortcode" class="form-control form-control-sm" placeholder="e.g. 174379">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Consumer Key') }}</label>
                            <input type="password" wire:model="daraja_consumer_key" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Consumer Secret') }}</label>
                            <input type="password" wire:model="daraja_consumer_secret" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Lipa Na M-Pesa Passkey') }}</label>
                            <input type="password" wire:model="daraja_passkey" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <label class="form-label small fw-bold text-dark mb-1">{{ __('STK Callback URL') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" readonly value="{{ url('/api/payment/daraja/stk-callback') }}">
                            <small class="text-muted d-block">
                                <i class="bi bi-info-circle me-1"></i> {{ __('Activates ONLY when ResultCode == 0. Cancelled or timed-out prompts never activate.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. PayHero Kenya --}}
            <div class="col-lg-6 col-12">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-circle me-2">
                                <i class="bi bi-wallet-fill fs-6"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-0">{{ __('PayHero Kenya') }}</h6>
                                <small class="text-muted">{{ __('Aggregated M-Pesa STK push & bank paybills with reverse verification') }}</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-5 mb-0">
                            <input class="form-check-input" type="checkbox" wire:model.live="payment_payhero_enabled" id="payheroSwitch">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('PayHero Channel ID') }}</label>
                            <input type="text" wire:model="payhero_channel_id" class="form-control form-control-sm" placeholder="e.g. 543">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('API Key / Token') }}</label>
                            <input type="password" wire:model="payhero_api_key" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('API Secret') }}</label>
                            <input type="password" wire:model="payhero_api_secret" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <label class="form-label small fw-bold text-dark mb-1">{{ __('Webhook URL') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" readonly value="{{ url('/api/payment/payhero/webhook') }}">
                            <small class="text-muted d-block">
                                <i class="bi bi-arrow-repeat me-1"></i> {{ __('Includes real-time reverse status verification with PayHero before fulfillment.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. KopoKopo --}}
            <div class="col-lg-6 col-12">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-warning-subtle text-warning p-2 rounded-circle me-2">
                                <i class="bi bi-shop fs-6"></i>
                            </span>
                            <div>
                                <h6 class="fw-bold mb-0">{{ __('KopoKopo (Buy Goods / STK)') }}</h6>
                                <small class="text-muted">{{ __('OAuth2 token caching, incoming payment webhooks and reverse verification') }}</small>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-5 mb-0">
                            <input class="form-check-input" type="checkbox" wire:model.live="payment_kopokopo_enabled" id="kopoSwitch">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Environment') }}</label>
                                <select wire:model="kopokopo_env" class="form-select form-select-sm">
                                    <option value="sandbox">{{ __('Sandbox') }}</option>
                                    <option value="production">{{ __('Production (Live)') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Till Number') }}</label>
                                <input type="text" wire:model="kopokopo_till_number" class="form-control form-control-sm" placeholder="e.g. K123456">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Client ID') }}</label>
                            <input type="password" wire:model="kopokopo_client_id" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Client Secret') }}</label>
                            <input type="password" wire:model="kopokopo_client_secret" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">{{ __('Webhook Secret (HMAC-SHA256)') }}</label>
                            <input type="password" wire:model="kopokopo_webhook_secret" class="form-control form-control-sm" placeholder="Stored encrypted">
                        </div>
                        <div class="p-3 bg-light rounded-3">
                            <label class="form-label small fw-bold text-dark mb-1">{{ __('Webhook URL') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" readonly value="{{ url('/api/payment/kopokopo/webhook') }}">
                            <small class="text-muted d-block">
                                <i class="bi bi-shield-check me-1"></i> {{ __('OAuth2 token cached in Redis/Cache for 7000s.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

