<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-chat-dots-fill text-primary me-2"></i>SMS & Email Gateway Hub</h3>
            <p class="text-muted mb-0">Configure system or custom Kenyan SMS providers, email SMTP, credit top-ups, and review delivery logs.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#testSmsModal">
                <i class="bi bi-send-check me-1"></i>Test SMS
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                <i class="bi bi-envelope-check me-1"></i>Test Email
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <span class="text-muted small fw-bold text-uppercase">ISP SMS Credit Balance</span>
                    <h3 class="fw-bold mb-0 text-primary">{{ number_format($walletBalance, 2) }} <small class="fs-6 text-muted">Units</small></h3>
                    <div class="small text-muted mt-1"><i class="bi bi-broadcast text-success me-1"></i>Platform Pool: <span class="fw-semibold text-secondary">{{ $carrierCredits }} live units</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-body">
                    <span class="text-muted small fw-bold text-uppercase">Delivered Messages (30d)</span>
                    <h3 class="fw-bold mb-0 text-success">{{ number_format($totalSent) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <span class="text-muted small fw-bold text-uppercase">Failed Deliveries</span>
                    <h3 class="fw-bold mb-0 text-danger">{{ number_format($totalFailed) }}</h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'gateways' ? 'active' : '' }}" wire:click="$set('activeTab', 'gateways')">
                <i class="bi bi-broadcast me-1"></i>SMS Gateways
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'email' ? 'active' : '' }}" wire:click="$set('activeTab', 'email')">
                <i class="bi bi-envelope me-1"></i>Email (SMTP)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $activeTab === 'logs' ? 'active' : '' }}" wire:click="$set('activeTab', 'logs')">
                <i class="bi bi-journal-text me-1"></i>Delivery Logs
            </button>
        </li>
    </ul>

    {{-- Tab 1: Gateways --}}
    @if($activeTab === 'gateways')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-sliders text-primary me-2"></i>Configure SMS Provider</h5>
            </div>
            <div class="card-body p-4">
                <form wire:submit.prevent="saveGateway">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Provider / Gateway Type</label>
                            <select wire:model.live="gateway_type" class="form-select">
                                <option value="system">Platform System Gateway (Shared)</option>
                                <option value="africastalking">Africa's Talking</option>
                                <option value="textsms">TextSMS / Celcom Africa</option>
                                <option value="blessedtexts">Blessed Texts</option>
                                <option value="advanta">Advanta SMS</option>
                                <option value="mobitech">Mobitech Technologies</option>
                                <option value="twilio">Twilio SMS</option>
                                <option value="generic_http">Generic HTTP / REST API</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Sender ID / Alphanumeric Tag</label>
                            <input type="text" wire:model="sender_id" class="form-control" placeholder="e.g. SMARTWIFI">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Username / Partner ID</label>
                            <input type="text" wire:model="api_username" class="form-control" placeholder="Username or Partner ID">
                        </div>

                        @if($gateway_type !== 'system')
                            <div class="col-md-6">
                                <label class="form-label fw-bold">API Key / Token</label>
                                <input type="password" wire:model="api_key" class="form-control" placeholder="••••••••">
                                <small class="text-muted">Encrypted at rest. Enter a new value to replace.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">API Secret (If required)</label>
                                <input type="password" wire:model="api_secret" class="form-control" placeholder="••••••••">
                            </div>
                        @endif

                        @if($gateway_type === 'generic_http')
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Endpoint URL</label>
                                <input type="text" wire:model="endpoint_url" class="form-control" placeholder="https://api.provider.com/v1/sms/send">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">HTTP Method</label>
                                <select wire:model="http_method" class="form-select">
                                    <option value="POST">POST</option>
                                    <option value="GET">GET</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">HTTP Headers (JSON)</label>
                                <textarea wire:model="http_headers" class="form-control font-monospace" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Body Template (use {to}, {message}, {sender})</label>
                                <textarea wire:model="body_template" class="form-control font-monospace" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Success Keyword</label>
                                <input type="text" wire:model="success_keyword" class="form-control" placeholder="e.g. success, 200, accepted">
                            </div>
                        @endif

                        <div class="col-12 mt-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" wire:model="is_default" id="chkDefault">
                                <label class="form-check-label fw-semibold" for="chkDefault">Set as Primary Gateway</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" wire:model="is_fallback" id="chkFallback">
                                <label class="form-check-label fw-semibold" for="chkFallback">Enable Fallback to System Gateway on Failure</label>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i>Save Gateway Configuration
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Tab 2: Email SMTP --}}
    @if($activeTab === 'email')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-envelope text-primary me-2"></i>Email Delivery (SMTP) Settings</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">SMTP Host</label>
                        <input type="text" wire:model="mail_host" class="form-control" placeholder="smtp.mailtrap.io">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Port</label>
                        <input type="number" wire:model="mail_port" class="form-control" placeholder="587">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Encryption</label>
                        <select wire:model="mail_encryption" class="form-select">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">SMTP Username</label>
                        <input type="text" wire:model="mail_username" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">SMTP Password</label>
                        <input type="password" wire:model="mail_password" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">From Email Address</label>
                        <input type="email" wire:model="mail_from_address" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">From Name</label>
                        <input type="text" wire:model="mail_from_name" class="form-control">
                    </div>
                    <div class="col-12 mt-4 text-end">
                        <button type="button" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i>Save SMTP Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 3: Delivery Logs --}}
    @if($activeTab === 'logs')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Real-Time Message Delivery Logs</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recipient</th>
                                <th>Channel</th>
                                <th>Provider</th>
                                <th>Message Snippet</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="fw-bold"><code>{{ $log->recipient }}</code></td>
                                    <td><span class="badge bg-light text-dark border">{{ strtoupper($log->channel) }}</span></td>
                                    <td class="text-muted">{{ $log->provider ?: 'System' }}</td>
                                    <td class="text-truncate" style="max-width: 250px;">{{ $log->content }}</td>
                                    <td>
                                        <span class="badge {{ $log->status === 'sent' || $log->status === 'delivered' ? 'bg-success' : 'bg-danger' }}">
                                            {{ strtoupper($log->status) }}
                                        </span>
                                    </td>
                                    <td>KES {{ number_format($log->cost, 2) }}</td>
                                    <td class="text-muted small">{{ $log->created_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No notification logs recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                {{ $logs->links() }}
            </div>
        </div>
    @endif

    {{-- Test SMS Modal --}}
    <div wire:ignore.self class="modal fade" id="testSmsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send-check text-primary me-2"></i>Send Test SMS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Recipient Mobile Number</label>
                        <input type="text" wire:model="test_phone" class="form-control" placeholder="0712345678 or 254712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <textarea wire:model="test_message" class="form-control" rows="3"></textarea>
                    </div>

                    @if($test_result)
                        <div class="alert {{ $test_result['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mb-0">
                            {{ $test_result['message'] }}
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" wire:click="sendTestSms" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Dispatch Test SMS
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Test Email Modal --}}
    <div wire:ignore.self class="modal fade" id="testEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-envelope-check text-primary me-2"></i>Send Test Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Recipient Email</label>
                        <input type="email" wire:model="test_email_recipient" class="form-control" placeholder="you@example.com">
                    </div>

                    @if($test_email_result)
                        <div class="alert {{ $test_email_result['status'] === 'success' ? 'alert-success' : 'alert-danger' }} mb-0">
                            {{ $test_email_result['message'] }}
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" wire:click="sendTestEmail" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Dispatch Test Email
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

