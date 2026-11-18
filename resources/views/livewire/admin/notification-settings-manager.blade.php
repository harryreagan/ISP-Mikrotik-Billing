<div>
    {{-- Page Header --}}
    <x-ui.page-header title="Notification & Alert Settings" subtitle="Configure automated SMS, WhatsApp, and Email notifications for offline routers, 12-hour escalations, and client lifecycle reminders.">
        <x-slot:actions>
            <button type="button" wire:click="saveSettings" wire:loading.attr="disabled" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2">
                <span wire:loading.remove wire:target="saveSettings"><i class="bi bi-check2-circle"></i> Save Settings</span>
                <span wire:loading wire:target="saveSettings"><span class="spinner-border spinner-border-sm me-1"></span> Saving...</span>
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Alerts & Feedback --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Section 1: Alert Recipients (ISP Owner Contacts) --}}
    <x-ui.card class="mb-4 p-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-person-badge me-2 text-primary"></i>ISP Owner Alert Contacts</h5>
                <p class="text-muted small mb-0">These contact details receive critical network downtime notifications, 12-hour offline reminders, and system alerts.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5"><i class="bi bi-shield-check me-1"></i>Protected Channels</span>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-secondary"><i class="bi bi-phone me-1"></i>Owner Phone (SMS)</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-chat-dots"></i></span>
                    <input type="text" wire:model="owner_phone" class="form-control" placeholder="e.g. 0712345678 or 254712345678">
                </div>
                <div class="form-text" style="font-size: 11px;">Receives router offline SMS alerts instantly.</div>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-secondary"><i class="bi bi-whatsapp me-1 text-success"></i>Owner WhatsApp Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-success"><i class="bi bi-whatsapp"></i></span>
                    <input type="text" wire:model="owner_whatsapp" class="form-control" placeholder="e.g. 254712345678">
                </div>
                <div class="form-text" style="font-size: 11px;">Receives rich WhatsApp downtime alerts.</div>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold text-secondary"><i class="bi bi-envelope me-1 text-danger"></i>Owner Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                    <input type="email" wire:model="owner_email" class="form-control" placeholder="admin@example.com">
                </div>
                <div class="form-text" style="font-size: 11px;">Receives detailed HTML escalation reports.</div>
            </div>
        </div>
    </x-ui.card>

    {{-- Section 2: Tabbed Navigation --}}
    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
        <ul class="nav nav-pills gap-1">
            <li class="nav-item">
                <button type="button" wire:click="setTab('network')" class="nav-link {{ $activeTab === 'network' ? 'active' : '' }} d-flex align-items-center gap-2">
                    <i class="bi bi-router"></i> Router &amp; Network Alerts
                    <span class="badge bg-danger rounded-pill" style="font-size: 10px;">Critical</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setTab('customers')" class="nav-link {{ $activeTab === 'customers' ? 'active' : '' }} d-flex align-items-center gap-2">
                    <i class="bi bi-people"></i> Subscriber Notifications
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setTab('templates')" class="nav-link {{ $activeTab === 'templates' ? 'active' : '' }} d-flex align-items-center gap-2">
                    <i class="bi bi-file-text"></i> Message Templates &amp; Tags
                </button>
            </li>
            <li class="nav-item">
                <button type="button" wire:click="setTab('logs')" class="nav-link {{ $activeTab === 'logs' ? 'active' : '' }} d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history"></i> Recent Logs
                </button>
            </li>
        </ul>

        {{-- Quick Toggle Controls --}}
        <div class="d-none d-md-flex align-items-center gap-2">
            <span class="small text-muted me-1">Quick Select:</span>
            <button type="button" wire:click="toggleChannelAll('sms', true)" class="btn btn-outline-secondary btn-sm px-2 py-1" style="font-size: 11px;" title="Enable SMS for all events">
                + All SMS
            </button>
            <button type="button" wire:click="toggleChannelAll('whatsapp', true)" class="btn btn-outline-success btn-sm px-2 py-1" style="font-size: 11px;" title="Enable WhatsApp for all events">
                + All WhatsApp
            </button>
            <button type="button" wire:click="toggleChannelAll('email', true)" class="btn btn-outline-danger btn-sm px-2 py-1" style="font-size: 11px;" title="Enable Email for all events">
                + All Email
            </button>
        </div>
    </div>

    {{-- TAB 1: Network & Router Alerts --}}
    @if($activeTab === 'network')
        <div class="mb-4">
            <div class="card border-0 shadow-xs mb-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-broadcast-pin text-danger me-2"></i>Router Uptime &amp; Offline Escalation Rules</h6>
                        <small class="text-muted">Just tick the channels you want enabled for each network event. Alerts are dispatched to the Owner Contacts configured above.</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 45%;">Network Event</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-phone me-1 text-primary"></i>SMS</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-envelope me-1 text-danger"></i>Email</th>
                                <th style="width: 10%;" class="text-center">Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($networkEvents as $key => $meta)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-start gap-2.5">
                                            @if($key === 'router_offline_alert')
                                                <div class="rounded-circle p-2 bg-danger-subtle text-danger"><i class="bi bi-exclamation-octagon-fill"></i></div>
                                            @elseif($key === 'router_offline_12h_reminder')
                                                <div class="rounded-circle p-2 bg-warning-subtle text-warning-emphasis"><i class="bi bi-alarm-fill"></i></div>
                                            @else
                                                <div class="rounded-circle p-2 bg-success-subtle text-success"><i class="bi bi-check-circle-fill"></i></div>
                                            @endif
                                            <div>
                                                <div class="fw-bold text-dark">{{ $meta['title'] }}</div>
                                                <div class="small text-muted">{{ $meta['description'] ?? '' }}</div>
                                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">Recipient: ISP Owner</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- SMS Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.sms" id="sms_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- WhatsApp Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.whatsapp" id="wa_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- Email Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.email" id="email_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- Quick Template Preview --}}
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" data-bs-toggle="collapse" data-bs-target="#preview_{{ $key }}" title="View message template">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse bg-light" id="preview_{{ $key }}">
                                    <td colspan="5" class="py-2.5 px-4">
                                        <div class="small">
                                            <strong>SMS / WhatsApp Template:</strong>
                                            <div class="font-monospace text-muted bg-white p-2 rounded border mt-1" style="font-size: 11px;">
                                                {{ $meta['sms_template'] ?? '' }}
                                            </div>
                                            <div class="text-secondary mt-1" style="font-size: 10px;">
                                                Dynamic tags: <code>{router}</code>, <code>{downtime}</code>, <code>{uptime_restored}</code>, <code>{active_users}</code>, <code>{location}</code>, <code>{isp_name}</code>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 2: Customer Lifecycle Notifications --}}
    @if($activeTab === 'customers')
        <div class="mb-4">
            <div class="card border-0 shadow-xs mb-3">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Subscriber Automated Notifications</h6>
                        <small class="text-muted">Tick the channels to send automated payment confirmations, expiry reminders, and welcome credentials to your clients.</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 45%;">Subscriber Event</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-phone me-1 text-primary"></i>SMS</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp</th>
                                <th style="width: 15%;" class="text-center"><i class="bi bi-envelope me-1 text-danger"></i>Email</th>
                                <th style="width: 10%;" class="text-center">Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customerEvents as $key => $meta)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-start gap-2.5">
                                            <div class="rounded-circle p-2 bg-primary-subtle text-primary"><i class="bi bi-bell-fill"></i></div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $meta['title'] }}</div>
                                                <div class="small text-muted">{{ $meta['description'] ?? '' }}</div>
                                                <span class="badge bg-light text-muted border" style="font-size: 10px;">Recipient: Subscriber Phone / Email</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- SMS Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.sms" id="sms_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- WhatsApp Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.whatsapp" id="wa_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- Email Checkbox --}}
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" role="switch" wire:model="events.{{ $key }}.email" id="email_{{ $key }}">
                                        </div>
                                    </td>

                                    {{-- Template Preview --}}
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-secondary btn-sm px-2" data-bs-toggle="collapse" data-bs-target="#preview_{{ $key }}" title="View message template">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse bg-light" id="preview_{{ $key }}">
                                    <td colspan="5" class="py-2.5 px-4">
                                        <div class="small">
                                            <strong>SMS / WhatsApp Template:</strong>
                                            <div class="font-monospace text-muted bg-white p-2 rounded border mt-1" style="font-size: 11px;">
                                                {{ $meta['sms_template'] ?? '' }}
                                            </div>
                                            <div class="text-secondary mt-1" style="font-size: 10px;">
                                                Dynamic tags: <code>{name}</code>, <code>{username}</code>, <code>{password}</code>, <code>{package}</code>, <code>{amount}</code>, <code>{expiry}</code>, <code>{paybill}</code>, <code>{trx_id}</code>, <code>{support_phone}</code>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: Message Templates & Tag Guide --}}
    @if($activeTab === 'templates')
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-xs p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-tags me-2 text-primary"></i>Available Dynamic Message Tags</h6>
                    <p class="text-muted small">The system automatically replaces these tags with real live data before dispatching SMS, WhatsApp, or Email:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Tag</th>
                                    <th>Replaced With</th>
                                    <th>Example Live Value</th>
                                </tr>
                            </thead>
                            <tbody class="small font-monospace">
                                <tr>
                                    <td><span class="text-primary">{router}</span></td>
                                    <td>MikroTik Router Name</td>
                                    <td class="text-muted">Router-HQ-Tower</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{downtime}</span></td>
                                    <td>Time router went offline</td>
                                    <td class="text-muted">14:30, 18 Nov</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{active_users}</span></td>
                                    <td>Number of affected connected clients</td>
                                    <td class="text-muted">42 subscribers</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{location}</span></td>
                                    <td>Station / Tower Location</td>
                                    <td class="text-muted">Westlands Hub</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{name}</span></td>
                                    <td>Customer Full Name</td>
                                    <td class="text-muted">Mary Wanjiku</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{username}</span></td>
                                    <td>PPPoE / Hotspot Username</td>
                                    <td class="text-muted">mary_home</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{package}</span></td>
                                    <td>Internet Package Name</td>
                                    <td class="text-muted">10 Mbps Home Fiber</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{amount}</span></td>
                                    <td>Billing amount in KES</td>
                                    <td class="text-muted">KES 1,500.00</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{paybill}</span></td>
                                    <td>ISP Paybill Number</td>
                                    <td class="text-muted">4082218</td>
                                </tr>
                                <tr>
                                    <td><span class="text-primary">{expiry}</span></td>
                                    <td>Package Expiry Date</td>
                                    <td class="text-muted">28 Nov 2026</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-xs p-4 bg-light">
                    <h6 class="fw-bold mb-2"><i class="bi bi-shield-check text-success me-2"></i>Alert Safeguards</h6>
                    <ul class="small text-muted ps-3 mb-0" style="line-height: 1.8;">
                        <li><strong>Deduplication Guard:</strong> Offline alerts are sent at most once on failure, preventing notification flood.</li>
                        <li><strong>12-Hour Escalation:</strong> If a router is continuously unreachable for 12 hours, a follow-up reminder is automatically dispatched.</li>
                        <li><strong>Auto-Recovery:</strong> A recovery confirmation is instantly sent when the router comes back online.</li>
                        <li><strong>Kenyan Phone Formatting:</strong> Numbers starting with <code>07...</code>, <code>01...</code>, or <code>+254</code> are normalized automatically.</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: Recent Logs --}}
    @if($activeTab === 'logs')
        <div class="card border-0 shadow-xs mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Notification Delivery Audit</h6>
                <span class="badge bg-light text-muted">Last 10 Dispatches</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light">
                        <tr>
                            <th>Channel</th>
                            <th>Event</th>
                            <th>Recipient</th>
                            <th>Message Preview</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLogs as $log)
                            <tr>
                                <td>
                                    @if($log->channel === 'sms')
                                        <span class="badge bg-primary-subtle text-primary"><i class="bi bi-phone me-1"></i>SMS</span>
                                    @elseif($log->channel === 'whatsapp')
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-whatsapp me-1"></i>WhatsApp</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-envelope me-1"></i>Email</span>
                                    @endif
                                </td>
                                <td class="fw-medium font-monospace">{{ $log->event_key }}</td>
                                <td class="font-monospace text-muted">{{ $log->recipient }}</td>
                                <td class="text-truncate" style="max-width: 250px;">{{ $log->content }}</td>
                                <td>
                                    @if($log->status === 'sent')
                                        <span class="badge bg-success text-white">Sent</span>
                                    @else
                                        <span class="badge bg-danger text-white">Failed</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No notification logs recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Bottom Section: Live Test Dispatcher --}}
    <x-ui.card class="p-4 border-0 shadow-sm mt-4 bg-white">
        <div class="row align-items-center g-3">
            <div class="col-12 col-lg-5">
                <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-send-check text-primary me-2"></i>Send Test Notification</h6>
                <p class="text-muted small mb-0">Verify your SMS, WhatsApp, or Email delivery right now by dispatching a sample alert to your owner contact.</p>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <select wire:model="test_event" class="form-select form-select-sm">
                    <option value="router_offline_alert">🔴 Router Offline Alert</option>
                    <option value="router_offline_12h_reminder">⏳ Router 12h Offline Reminder</option>
                    <option value="router_restored_alert">🟢 Router Back Online (Recovery)</option>
                    <option value="payment_received">💳 Payment Received Receipt</option>
                    <option value="welcome">👤 Customer Welcome Credentials</option>
                </select>
            </div>

            <div class="col-12 col-sm-6 col-lg-2">
                <select wire:model="test_channel" class="form-select form-select-sm">
                    <option value="sms">📱 SMS</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="email">✉️ Email</option>
                </select>
            </div>

            <div class="col-12 col-lg-2">
                <button type="button" wire:click="sendTestAlert" wire:loading.attr="disabled" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1.5 py-1.5">
                    <span wire:loading.remove wire:target="sendTestAlert"><i class="bi bi-broadcast"></i> Send Test</span>
                    <span wire:loading wire:target="sendTestAlert"><span class="spinner-border spinner-border-sm me-1"></span> Testing...</span>
                </button>
            </div>
        </div>

        @if($test_result)
            <div class="alert alert-{{ $test_status_type }} mt-3 mb-0 py-2 small d-flex align-items-center justify-content-between" role="alert">
                <div>
                    @if($test_status_type === 'success')
                        <i class="bi bi-check-circle-fill me-2 text-success"></i>
                    @else
                        <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                    @endif
                    {{ $test_result }}
                </div>
                <button type="button" class="btn-close btn-sm" wire:click="$set('test_result', null)"></button>
            </div>
        @endif
    </x-ui.card>
</div>

