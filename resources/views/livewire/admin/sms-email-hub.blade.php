<div class="container-fluid py-4" style="max-width: 1400px;">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-chat-square-quote-fill text-warning me-2"></i>SMS &amp; Email Gateway Hub
            </h2>
            <p class="text-muted small mb-0">Unified customer messaging, SMS campaigns, gateway credentials and delivery audit logs.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold fs-6">
                <i class="bi bi-chat-left-dots-fill text-primary me-1"></i> Balance: <strong class="text-primary">{{ $smsBalance }}</strong> Units
            </span>
            <button type="button" class="btn btn-primary btn-sm px-3 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#testSmsModal">
                <i class="bi bi-send-fill me-1"></i> Test SMS
            </button>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if($campaignStatus)
        <div class="alert alert-{{ $campaignStatusType === 'error' ? 'danger' : 'success' }} alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi {{ $campaignStatusType === 'error' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill' }} fs-5 me-2"></i>
            {{ $campaignStatus }}
            <button type="button" wire:click="$set('campaignStatus', null)" class="btn-close" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabs Navigation --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill gap-2">
                <li class="nav-item">
                    <button class="nav-link py-2.5 fw-bold {{ $activeTab === 'campaigns' ? 'active' : '' }}" 
                            wire:click="setTab('campaigns')">
                        <i class="bi bi-broadcast me-1"></i> Send SMS / Campaigns
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-2.5 fw-bold {{ $activeTab === 'gateways' ? 'active' : '' }}" 
                            wire:click="setTab('gateways')">
                        <i class="bi bi-gear-wide-connected me-1"></i> Gateways (SMS &amp; SMTP)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-2.5 fw-bold {{ $activeTab === 'templates' ? 'active' : '' }}" 
                            wire:click="setTab('templates')">
                        <i class="bi bi-file-earmark-text me-1"></i> Message Templates
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-2.5 fw-bold {{ $activeTab === 'bridge' ? 'active' : '' }}" 
                            wire:click="setTab('bridge')">
                        <i class="bi bi-phone me-1"></i> SMS Bridge Webhook
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-2.5 fw-bold {{ $activeTab === 'logs' ? 'active' : '' }}" 
                            wire:click="setTab('logs')">
                        <i class="bi bi-journal-check me-1"></i> Delivery Logs
                    </button>
                </li>
            </ul>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: CAMPAIGNS / SEND SMS                                    --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'campaigns')
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold text-dark mb-3">Compose Campaign / Direct SMS</h4>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Target Recipient Group</label>
                            <select wire:model.live="recipientGroup" class="form-select form-select-lg">
                                <option value="all">👥 All Customers</option>
                                <option value="active">🟢 Active Customers Only</option>
                                <option value="inactive">🔴 Inactive / Expired Customers Only</option>
                                <option value="due">⚠️ Due / Pending Bill Customers</option>
                                <option value="custom">📱 Custom Phone Number(s)</option>
                            </select>
                        </div>

                        @if($recipientGroup === 'custom')
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Mobile Number(s)</label>
                                <input type="text" wire:model="customPhone" class="form-control" placeholder="e.g. 0712345678, 0722000000 (comma-separated)">
                                <div class="form-text">Supports local formats: 07XXXXXXXX, 01XXXXXXXX, or 2547XXXXXXXX.</div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Template (Optional)</label>
                                @if(!empty($templatesList))
                                    <div class="small">
                                        <select wire:model.live="selectedTemplateKey" wire:change="applyTemplateToMessage" class="form-select form-select-sm">
                                            <option value="">Insert template...</option>
                                            @foreach($templatesList as $t)
                                                @php $tArr = (array) $t; @endphp
                                                <option value="{{ $tArr['id'] ?? $tArr['title'] }}">{{ $tArr['title'] ?? 'Template' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>

                            <textarea wire:model="messageContent" rows="5" class="form-control" placeholder="Write your announcement or notice here... Available placeholders: {name}, {phone}, {site_name}"></textarea>
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>Placeholders: <code>{name}</code>, <code>{phone}</code>, <code>{site_name}</code></span>
                                <span>{{ strlen($messageContent) }} chars ({{ ceil(max(strlen($messageContent), 1) / 160) }} SMS)</span>
                            </div>
                        </div>

                        <button type="button" wire:click="sendCampaign" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                            <i class="bi bi-send-fill me-2"></i> Broadcast Message
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm bg-light" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3">Gateway Routing &amp; Status</h5>
                        <div class="p-3 bg-white rounded-3 shadow-sm mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Current Provider:</span>
                                <span class="badge bg-success-subtle text-success border border-success fw-bold text-uppercase">
                                    {{ $gateway_type }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Sender ID:</span>
                                <span class="fw-bold text-dark">{{ $sender_id ?: 'HSMART' }}</span>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 mb-0 small">
                            <i class="bi bi-info-circle-fill me-1"></i> Messages are dispatched through your custom Kenyan SMS gateway or the platform shared pool. Failed deliveries automatically fall back to the backup route.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: GATEWAYS (SMS & SMTP)                                    --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'gateways')
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold text-dark mb-4">Kenyan SMS Gateway Provider</h4>

                        <form wire:submit.prevent="saveGateway">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Provider / Engine</label>
                                    <select wire:model="gateway_type" class="form-select form-select-lg">
                                        <option value="africastalking">Africa's Talking (Kenya)</option>
                                        <option value="textsms">TextSMS (SMSLeopard / Advanta API)</option>
                                        <option value="advanta">Advanta SMS</option>
                                        <option value="blessed">Blessed Texts</option>
                                        <option value="system">Platform Shared Pool (Auto Managed)</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">Sender ID / Alphanumeric Tag</label>
                                    <input type="text" wire:model="sender_id" class="form-control form-control-lg" placeholder="e.g. HSMART or Your Brand">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">API Username / Partner ID</label>
                                    <input type="text" wire:model="api_username" class="form-control" placeholder="Username or Partner ID">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold">API Key / Token</label>
                                    <input type="password" wire:model="api_key" class="form-control" placeholder="••••••••••••••••">
                                    <div class="form-text">Encrypted at rest with AES-256.</div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox" wire:model="is_fallback" id="fallbackSwitch">
                                        <label class="form-check-label fw-semibold" for="fallbackSwitch">
                                            Enable Platform Fallback (dispatches via system gateway if custom provider fails)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary px-4 py-2.5 fw-bold">
                                    <i class="bi bi-save me-1"></i> Save Gateway Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3">Email (SMTP) Settings</h5>
                        <p class="text-muted small">Emails for password resets, invoices and payment receipts.</p>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">SMTP Host</label>
                            <input type="text" wire:model="mail_host" class="form-control form-control-sm" placeholder="smtp.gmail.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Port &amp; Encryption</label>
                            <div class="input-group input-group-sm">
                                <input type="number" wire:model="mail_port" class="form-control" placeholder="587">
                                <select wire:model="mail_encryption" class="form-select">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">From Address</label>
                            <input type="email" wire:model="mail_from_address" class="form-control form-control-sm" placeholder="billing@yourdomain.com">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3: TEMPLATES                                               --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'templates')
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h4 class="fw-bold text-dark mb-3">Notification Templates</h4>
                <p class="text-muted small mb-4">Standardized templates automatically sent on customer life-cycle events.</p>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="card bg-light border p-3 rounded-3 h-100">
                            <div class="fw-bold text-primary mb-1"><i class="bi bi-box-arrow-in-right me-1"></i> Customer Welcome &amp; Login</div>
                            <p class="text-dark small mb-2">"Hello {name}, welcome to {site_name}! Your account is active. Connect anytime."</p>
                            <span class="badge bg-secondary-subtle text-secondary small align-self-start">Auto-event</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card bg-light border p-3 rounded-3 h-100">
                            <div class="fw-bold text-success mb-1"><i class="bi bi-cash-coin me-1"></i> Payment Received (Receipt)</div>
                            <p class="text-dark small mb-2">"Confirmed! Received KES {amount} for {package}. Receipt: {receipt}. Valid until: {expiry}."</p>
                            <span class="badge bg-success-subtle text-success small align-self-start">M-Pesa Webhook</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card bg-light border p-3 rounded-3 h-100">
                            <div class="fw-bold text-warning mb-1"><i class="bi bi-clock-history me-1"></i> Package Expiring Warning</div>
                            <p class="text-dark small mb-2">"Dear {name}, your internet package {package} expires on {expiry}. Renew via M-Pesa to prevent disconnection."</p>
                            <span class="badge bg-warning-subtle text-warning small align-self-start">Automated Cron</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="card bg-light border p-3 rounded-3 h-100">
                            <div class="fw-bold text-danger mb-1"><i class="bi bi-shield-lock me-1"></i> OTP / Password Reset</div>
                            <p class="text-dark small mb-2">"Your {site_name} verification code is: {code}. Do not share this code with anyone. Expires in 5 minutes."</p>
                            <span class="badge bg-danger-subtle text-danger small align-self-start">Security Auth</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 4: SMS BRIDGE WEBHOOK                                      --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'bridge')
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h4 class="fw-bold text-dark mb-2">SMS Android Forwarder &amp; Webhook Bridge</h4>
                <p class="text-muted small mb-4">Receive SMS payments from physical Android SIM phones and auto-credit customers.</p>

                <div class="card bg-light border-0 p-4 rounded-3 mb-4">
                    <label class="form-label fw-bold text-dark">Your M-Pesa SMS Receiver Webhook URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-white font-monospace" readonly value="https://isp.hsmart.co.ke/api/payment/mfs/sms-receiver">
                        <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText('https://isp.hsmart.co.ke/api/payment/mfs/sms-receiver'); alert('Webhook URL copied!');">
                            <i class="bi bi-clipboard me-1"></i> Copy URL
                        </button>
                    </div>
                    <div class="form-text mt-2">
                        Install "SMS Forwarder" on your Android phone, set this URL as Target Webhook with method POST, and forward incoming M-Pesa SMS messages.
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 5: DELIVERY LOGS                                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'logs')
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h4 class="fw-bold text-dark mb-3">Live Dispatch Logs</h4>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recipient</th>
                                <th>Channel</th>
                                <th>Content</th>
                                <th>Status</th>
                                <th>Provider</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="fw-bold">{{ $log->recipient }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ strtoupper($log->channel) }}</span></td>
                                    <td><div class="text-truncate" style="max-width: 320px;">{{ $log->content }}</div></td>
                                    <td>
                                        <span class="badge {{ $log->status === 'sent' ? 'bg-success' : 'bg-danger' }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td><small class="text-muted">{{ $log->provider }}</small></td>
                                    <td><small class="text-muted">{{ $log->created_at }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No notification logs recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    @endif

    {{-- Test SMS Modal --}}
    <div class="modal fade" id="testSmsModal" tabindex="-1" aria-labelledby="testSmsModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="testSmsModalLabel"><i class="bi bi-send text-primary me-2"></i>Send Test SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Recipient Mobile Number</label>
                        <input type="text" wire:model="test_phone" class="form-control" placeholder="e.g. 0743606108">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <textarea wire:model="test_message" class="form-control" rows="3"></textarea>
                    </div>

                    @if($test_result)
                        <div class="alert {{ str_contains($test_result, 'successfully') ? 'alert-success' : 'alert-danger' }} border-0 py-2 small mb-0">
                            {{ $test_result }}
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" wire:click="sendTestSms" class="btn btn-primary btn-sm px-3 fw-bold">
                        <i class="bi bi-send-fill me-1"></i> Dispatch Test SMS
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

