<div class="container-fluid px-3 py-4">

    {{-- ── Header ── --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-building-gear me-1"></i> Plug & Play Integration
                </span>
                <span class="badge {{ $is_enabled ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-2 py-1 rounded-pill">
                    {{ $is_enabled ? '● Active' : '○ Disabled' }}
                </span>
            </div>
            <h2 class="h4 fw-bold text-dark mb-0">Hotel & POS WiFi Integration</h2>
            <p class="text-muted small mb-0">Automate complimentary WiFi tickets printed on hotel check-in folios & POS thermal receipts for HSmart and 3rd-party systems.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mt-md-0">
            @if($isSuperAdmin && $tenants->isNotEmpty())
                <div class="d-flex align-items-center gap-2 me-2 bg-light p-1 px-2 rounded-pill border">
                    <span class="small fw-bold text-muted"><i class="bi bi-shield-check text-primary"></i> Super Admin Hotel:</span>
                    <select class="form-select form-select-sm border-0 bg-transparent fw-semibold" wire:model.live="selected_tenant_id" style="min-width: 180px;">
                        @foreach($tenants as $ten)
                            <option value="{{ $ten->id }}">{{ $ten->name }} (ID: {{ $ten->id }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="button" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#superAdminManualModal">
                <i class="bi bi-journal-bookmark-fill me-1"></i> Super Admin Manual
            </button>
            <a href="{{ route('admin.portal-customizer') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-palette me-1"></i> Captive Portal
            </a>
            <button wire:click="saveSettings" type="button" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Save Changes
            </button>
        </div>
    </div>

    {{-- ── Alerts ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Section 1: API Key & Endpoint Configuration ── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="rounded-3 p-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width:36px; height:36px;">
                    <i class="bi bi-key-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Tenant REST API Credentials & Webhook</h6>
                    <small class="text-muted">Use this API key in your external Hotel PMS (Opera, Cloudbeds) or POS systems to generate tickets automatically.</small>
                </div>
            </div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="enableSwitch" wire:model.live="is_enabled">
                <label class="form-check-label fw-semibold small" for="enableSwitch">API Enabled</label>
            </div>
        </div>
        <div class="card-body p-4 bg-light bg-opacity-50">
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Hotel / Restaurant Display Name</label>
                    <input type="text" class="form-control bg-white fw-semibold" wire:model="business_name" placeholder="e.g. Grand Safari Hotel & Cafe">
                    <small class="text-muted mt-1 d-block">Shown on guest thermal receipt slips and staff reception console.</small>
                </div>
                <div class="col-lg-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">
                        <i class="bi bi-display text-primary me-1"></i> Hotel Staff Reception & POS Console (No ISP Login Required)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-shield-check"></i></span>
                        <input type="text" class="form-control bg-white font-monospace small" readonly value="{{ route('hospitality.client-console', ['token' => $portal_token]) }}" id="clientPortalUrlInput">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('clientPortalUrlInput').value); alert('Staff Portal Link copied to clipboard!');">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                        <a href="{{ route('hospitality.client-console', ['token' => $portal_token]) }}" target="_blank" class="btn btn-primary" title="Open Staff Console">
                            <i class="bi bi-box-arrow-up-right"></i> Open
                        </a>
                        <button wire:click="regeneratePortalToken" wire:confirm="Regenerate this staff portal link? Old link will stop working." class="btn btn-outline-danger" type="button" title="Regenerate Link">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                    <small class="text-muted mt-1 d-block">Give this link to the hotel receptionist or cashier. They can issue tickets and view active rooms without accessing your ISP backend!</small>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">API Endpoint URL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-globe"></i></span>
                        <input type="text" class="form-control bg-white font-monospace small" readonly value="{{ url('/api/v1/hospitality/ticket') }}" id="apiEndpointInput">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('apiEndpointInput').value); alert('Endpoint copied to clipboard!');">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <small class="text-muted mt-1 d-block">Method: <code>POST</code> | Header: <code>X-API-Key</code></small>
                </div>

                <div class="col-lg-6">
                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Tenant Secret API Key</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-shield-lock"></i></span>
                        <input type="password" class="form-control bg-white font-monospace small" readonly value="{{ $api_key }}" id="apiKeyInput">
                        <button class="btn btn-outline-secondary" type="button" onclick="const f=document.getElementById('apiKeyInput'); if(f.type==='password'){f.type='text';}else{f.type='password';}">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('apiKeyInput').value); alert('API Key copied to clipboard!');">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                        <button wire:click="regenerateApiKey" wire:confirm="Are you sure you want to regenerate this API key? Existing integrations will stop working until updated." class="btn btn-outline-danger" type="button" title="Regenerate Key">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                    <small class="text-muted mt-1 d-block">Keep secret. Authorizes ticket generation on your MikroTik routers.</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section 2: Hotel & POS WiFi Rules ── --}}
    <div class="row g-4 mb-4">
        {{-- Hotel PMS Rules --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center gap-2">
                    <i class="bi bi-hospital fs-5 text-primary"></i>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Hotel Check-in & Room Folio Rules</h6>
                        <small class="text-muted">Settings for hotel guest folios and room check-in billing</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Default Hotel Hotspot Package</label>
                        <select class="form-select" wire:model="default_hotel_package_id">
                            <option value="">-- Use First Active Router Package --</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}">{{ $pkg->name }} ({{ $pkg->speed_limit ?: 'Full Speed' }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Bandwidth profile assigned to hotel guest accounts.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Default Stay Duration</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="1" wire:model="hotel_duration_hours">
                                <span class="input-group-text text-muted">Hours</span>
                            </div>
                            <small class="text-muted">Per night or default check-in stay.</small>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Max Devices Per Room</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="1" max="10" wire:model="hotel_device_limit">
                                <span class="input-group-text text-muted">Devices</span>
                            </div>
                            <small class="text-muted">Phones/laptops permitted per voucher.</small>
                        </div>
                    </div>

                    <div class="form-check form-switch pt-2 border-top">
                        <input class="form-check-input" type="checkbox" id="roomLoginSwitch" wire:model="allow_room_guest_login">
                        <label class="form-check-label fw-semibold small" for="roomLoginSwitch">
                            Allow Room # + Guest Name direct login on Captive Portal
                        </label>
                        <small class="d-block text-muted">Guests can type their room number on the WiFi splash screen without a voucher code.</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- POS & Restaurant Rules --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff fs-5 text-success"></i>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">POS & Restaurant Receipt Rules</h6>
                        <small class="text-muted">Automatic WiFi tickets for retail, cafe, and dining bills</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Default POS Hotspot Package</label>
                        <select class="form-select" wire:model="default_pos_package_id">
                            <option value="">-- Use First Active Router Package --</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}">{{ $pkg->name }} ({{ $pkg->speed_limit ?: 'Standard' }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Speed profile assigned to dining customers.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Minimum Bill Spend</label>
                            <div class="input-group">
                                <span class="input-group-text text-muted">KES</span>
                                <input type="number" step="10" class="form-control" min="0" wire:model="pos_min_spend">
                            </div>
                            <small class="text-muted">Only bills ≥ this spend receive a ticket.</small>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Customer Session Duration</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="1" wire:model="pos_duration_hours">
                                <span class="input-group-text text-muted">Hours</span>
                            </div>
                            <small class="text-muted">e.g. 2 Hours for coffee/dining.</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Designated Router</label>
                            <select class="form-select" wire:model="selected_router_id">
                                <option value="">-- Automatic / Primary Router --</option>
                                @foreach($routers as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->ip_address }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Receipt Header Text</label>
                            <input type="text" class="form-control" wire:model="receipt_header" placeholder="COMPLIMENTARY WI-FI">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section 3: Live Quick Test Generator & Thermal Slip Preview ── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-printer fs-5 text-dark"></i>
                <h6 class="fw-bold mb-0 text-dark">Test Ticket Generator & Thermal Receipt Preview</h6>
            </div>
            <span class="badge bg-light text-muted border">Simulation Mode</span>
        </div>
        <div class="card-body p-4">
            @if(session('ticket_success'))
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-check2-circle fs-5"></i>
                    <div>{{ session('ticket_success') }}</div>
                </div>
            @endif
            @if(session('ticket_error'))
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle fs-5"></i>
                    <div>{{ session('ticket_error') }}</div>
                </div>
            @endif

            <div class="row g-4">
                {{-- Form Left --}}
                <div class="col-lg-6">
                    <form wire:submit.prevent="issueTestTicket">
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Ticket Type</label>
                                <select class="form-select" wire:model.live="test_type">
                                    <option value="pos_receipt">POS / Restaurant Receipt</option>
                                    <option value="hotel_guest">Hotel Guest Folio</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Customer / Guest Name</label>
                                <input type="text" class="form-control" wire:model="test_guest_name">
                            </div>
                        </div>

                        @if($test_type === 'hotel_guest')
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Room Number</label>
                                    <input type="text" class="form-control" wire:model="test_room_number" placeholder="e.g. Room 204">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Booking / Folio Ref</label>
                                    <input type="text" class="form-control" wire:model="test_receipt_ref" placeholder="e.g. HTL-9921">
                                </div>
                            </div>
                        @else
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Bill Total (KES)</label>
                                    <input type="number" class="form-control" wire:model="test_bill_amount">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Receipt Reference</label>
                                    <input type="text" class="form-control" wire:model="test_receipt_ref">
                                </div>
                            </div>
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Duration (Hours)</label>
                                <input type="number" class="form-control" min="1" wire:model="test_duration_hours">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold small">Device Limit</label>
                                <input type="number" class="form-control" min="1" max="10" wire:model="test_device_limit">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark rounded-pill px-4">
                            <i class="bi bi-ticket-perforated me-1"></i> Issue Test Ticket
                        </button>
                    </form>
                </div>

                {{-- Thermal Receipt Preview Right --}}
                <div class="col-lg-6">
                    <div class="p-3 bg-light rounded-4 border">
                        <small class="fw-bold text-muted text-uppercase d-block mb-2">
                            <i class="bi bi-receipt me-1"></i> Simulated 58mm / 80mm ESC/POS Receipt Output
                        </small>
                        <div class="bg-white p-3 rounded-3 shadow-sm font-monospace text-dark text-start" style="font-size: 11px; max-width: 340px; margin: 0 auto; border: 1px dashed #bbb;">
                            <div class="text-center pb-2 border-bottom">
                                <div class="fw-bold">YOUR HOTEL & CAFE</div>
                                <div class="small text-muted">Bill Receipt: {{ $test_receipt_ref }}</div>
                            </div>

                            <pre class="m-0 py-2" style="white-space: pre-wrap; font-family: monospace; font-size: 10.5px;">{{ $generatedTicket['printable_text'] ?? "================================\n  " . strtoupper($receipt_header) . "\n================================\nNetwork (SSID) : HSmart_WiFi\nWiFi Code      : HTL-SAMPLE\nDuration       : " . $test_duration_hours . " Hour(s)\nDevice Limit   : " . $test_device_limit . " Device(s)\n--------------------------------\n   Scan QR code to auto-connect \n  " . $receipt_footer . "\n================================" }}</pre>

                            @if(!empty($generatedTicket['qr_url']))
                                <div class="text-center pt-2 border-top">
                                    <div class="small fw-bold text-muted mb-1">[ 1-CLICK QR LOGIN LINK ]</div>
                                    <a href="{{ $generatedTicket['qr_url'] }}" target="_blank" class="btn btn-outline-primary btn-sm py-0 px-2 rounded-pill" style="font-size: 10px;">
                                        Test Portal Auto-Login <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Section 4: Interactive Developer Integration Guide ── --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-code-slash fs-5 text-indigo"></i>
                <h6 class="fw-bold mb-0 text-dark">Plug & Play Developer Snippets (For External Systems)</h6>
            </div>
            <small class="text-muted">Opera PMS • Cloudbeds • Loyverse • Custom POS</small>
        </div>
        <div class="card-body p-4">
            <ul class="nav nav-pills gap-2 mb-3">
                <li class="nav-item">
                    <button class="nav-link py-1 px-3 rounded-pill {{ $activeDocTab === 'curl' ? 'active' : '' }}" wire:click="$set('activeDocTab', 'curl')">cURL</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-1 px-3 rounded-pill {{ $activeDocTab === 'php' ? 'active' : '' }}" wire:click="$set('activeDocTab', 'php')">PHP / Laravel</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-1 px-3 rounded-pill {{ $activeDocTab === 'js' ? 'active' : '' }}" wire:click="$set('activeDocTab', 'js')">JavaScript / Node.js</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-1 px-3 rounded-pill {{ $activeDocTab === 'python' ? 'active' : '' }}" wire:click="$set('activeDocTab', 'python')">Python</button>
                </li>
            </ul>

            <div class="position-relative">
                @if($activeDocTab === 'curl')
<pre class="bg-dark text-light p-3 rounded-3 small font-monospace m-0"><code>curl -X POST "{{ url('/api/v1/hospitality/ticket') }}" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: {{ $api_key }}" \
  -d '{
    "ticket_type": "pos_receipt",
    "source_system": "external_pos",
    "guest_name": "John Doe",
    "receipt_ref": "REC-9481",
    "bill_amount": 250.00,
    "duration_hours": 2,
    "device_limit": 1
  }'</code></pre>
                @elseif($activeDocTab === 'php')
<pre class="bg-dark text-light p-3 rounded-3 small font-monospace m-0"><code>use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'X-API-Key' => '{{ $api_key }}',
])->post('{{ url('/api/v1/hospitality/ticket') }}', [
    'ticket_type'    => 'hotel_guest',
    'source_system'  => 'opera_pms',
    'guest_name'     => $booking->guest_name,
    'room_number'    => $booking->room_number,
    'nights'         => $booking->nights,
    'device_limit'   => 3,
]);

$ticketCode    = $response->json('code');
$qrLoginUrl    = $response->json('qr_url');
$printableSlip = $response->json('printable_text'); // Ready for ESC/POS receipt</code></pre>
                @elseif($activeDocTab === 'js')
<pre class="bg-dark text-light p-3 rounded-3 small font-monospace m-0"><code>const res = await fetch('{{ url('/api/v1/hospitality/ticket') }}', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': '{{ $api_key }}'
  },
  body: JSON.stringify({
    ticket_type: 'pos_receipt',
    receipt_ref: 'INV-4820',
    bill_amount: 500,
    guest_name: 'Walk-in Customer'
  })
});
const data = await res.json();
console.log('WiFi Code:', data.code, 'QR Link:', data.qr_url);</code></pre>
                @elseif($activeDocTab === 'python')
<pre class="bg-dark text-light p-3 rounded-3 small font-monospace m-0"><code>import requests

headers = {
    'Content-Type': 'application/json',
    'X-API-Key': '{{ $api_key }}'
}
payload = {
    'ticket_type': 'hotel_guest',
    'guest_name': 'Alice Smith',
    'room_number': '108',
    'duration_hours': 72,
    'device_limit': 2
}
response = requests.post('{{ url('/api/v1/hospitality/ticket') }}', json=payload, headers=headers)
print(response.json())</code></pre>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Section 5: Issued Hospitality Tickets History ── --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h6 class="fw-bold mb-0 text-dark">Issued Hospitality Tickets & Folio Log</h6>
                <small class="text-muted">History of WiFi vouchers granted through Hotel PMS and POS systems.</small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-sm" placeholder="Search code, room, guest..." wire:model.live.debounce.300ms="search" style="width: 220px;">
                <select class="form-select form-select-sm" wire:model.live="typeFilter" style="width: 140px;">
                    <option value="all">All Types</option>
                    <option value="hotel_guest">Hotel Guest</option>
                    <option value="pos_receipt">POS Receipt</option>
                </select>
                <select class="form-select form-select-sm" wire:model.live="statusFilter" style="width: 120px;">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="used">Used</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Ticket Code</th>
                        <th>Type</th>
                        <th>Room / Ref</th>
                        <th>Guest / Customer</th>
                        <th>Duration</th>
                        <th>Device Limit</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($tickets as $t)
                        <tr>
                            <td class="ps-4">
                                <span class="font-monospace fw-bold text-dark">{{ $t->ticket_code }}</span>
                            </td>
                            <td>
                                @if($t->ticket_type === 'hotel_guest')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-hospital me-1"></i> Hotel Room
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-receipt me-1"></i> POS Receipt
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($t->room_number)
                                    <span class="fw-bold">Room {{ $t->room_number }}</span>
                                @endif
                                @if($t->receipt_ref)
                                    <span class="text-muted d-block small">{{ $t->receipt_ref }}</span>
                                @endif
                                @if(!$t->room_number && !$t->receipt_ref)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $t->guest_name ?: 'Guest' }}</div>
                                @if($t->bill_amount)
                                    <small class="text-muted">Spend: KES {{ number_format($t->bill_amount, 2) }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $t->duration_hours >= 24 ? (int)($t->duration_hours / 24) . ' Day(s)' : $t->duration_hours . ' Hour(s)' }}
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">{{ $t->device_limit }} device(s)</span>
                            </td>
                            <td>
                                @if($t->status === 'active')
                                    <span class="badge bg-success text-white">Active</span>
                                @elseif($t->status === 'revoked')
                                    <span class="badge bg-danger text-white">Revoked</span>
                                @else
                                    <span class="badge bg-secondary text-white">{{ ucfirst($t->status) }}</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                {{ $t->created_at->diffForHumans() }}
                            </td>
                            <td class="pe-4 text-end">
                                @if($t->status === 'active')
                                    <button wire:click="revokeTicket({{ $t->id }})" wire:confirm="Revoke ticket {{ $t->ticket_code }}?" class="btn btn-outline-danger btn-sm py-0 px-2 rounded-pill" title="Revoke Ticket">
                                        Revoke
                                    </button>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-ticket-perforated fs-3 d-block mb-2 text-secondary"></i>
                                No hospitality tickets generated yet. Generate a test ticket above or integrate via REST API.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="card-footer bg-white border-top py-3 px-4">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>

    {{-- ── Super Admin Interactive Step-by-Step Manual Modal ── --}}
    <div wire:ignore.self class="modal fade" id="superAdminManualModal" tabindex="-1" aria-labelledby="superAdminManualLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header bg-dark text-white py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark-fill text-warning fs-5"></i>
                        <div>
                            <h6 class="modal-title fw-bold mb-0 text-white" id="superAdminManualLabel">Super Admin Master Procedure: Hotel & POS WiFi Onboarding</h6>
                            <small class="text-white-50">Quick-reference playbook on how to onboard hotel clients, hand over staff portals, and connect external POS systems.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 bg-light">
                    {{-- Quick Workflow Steps --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="bg-white p-3 rounded-3 border h-100 shadow-sm text-center">
                                <div class="badge bg-primary rounded-circle mb-2" style="width:28px; height:28px; line-height:20px;">1</div>
                                <h6 class="fw-bold mb-1 small text-dark">Switch Hotel Account</h6>
                                <p class="text-muted small mb-0" style="font-size: 11px;">Pick client in Super Admin switcher at top right.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white p-3 rounded-3 border h-100 shadow-sm text-center">
                                <div class="badge bg-primary rounded-circle mb-2" style="width:28px; height:28px; line-height:20px;">2</div>
                                <h6 class="fw-bold mb-1 small text-dark">Configure Stay Rules</h6>
                                <p class="text-muted small mb-0" style="font-size: 11px;">Set duration (24h/night), devices, and min spend.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white p-3 rounded-3 border h-100 shadow-sm text-center">
                                <div class="badge bg-primary rounded-circle mb-2" style="width:28px; height:28px; line-height:20px;">3</div>
                                <h6 class="fw-bold mb-1 small text-dark">Choose Handover Mode</h6>
                                <p class="text-muted small mb-0" style="font-size: 11px;">Staff Portal (No-Code) OR REST API for POS.</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white p-3 rounded-3 border h-100 shadow-sm text-center">
                                <div class="badge bg-success rounded-circle mb-2" style="width:28px; height:28px; line-height:20px;">4</div>
                                <h6 class="fw-bold mb-1 small text-dark">Guests Scan & Connect</h6>
                                <p class="text-muted small mb-0" style="font-size: 11px;">Receipt QR code logs guest in with 0 typing.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 1 Details --}}
                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-header bg-white py-2 px-3 fw-bold small text-dark d-flex align-items-center gap-2">
                            <span class="badge bg-primary">STEP 1</span> Setting Up a New Hotel Client in 30 Seconds
                        </div>
                        <div class="card-body p-3 small text-muted">
                            <ol class="mb-0 ps-3">
                                <li class="mb-1">Select the hotel operator using the <strong>Super Admin Hotel Switcher</strong> dropdown at the top right of this page.</li>
                                <li class="mb-1">Enter their <strong>Hotel / Restaurant Display Name</strong> (e.g. <em>Seaside Safari Resort & Cafe</em>).</li>
                                <li class="mb-1">Select their <strong>Default Hotspot Speed Package</strong> (e.g. 10 Mbps) and <strong>Designated MikroTik Router</strong>.</li>
                                <li class="mb-1">Set <strong>Default Stay Duration</strong> (e.g. 24 hours per night) and <strong>Max Devices Per Room</strong> (e.g. 3 devices).</li>
                                <li>Click <span class="badge bg-primary">Save Changes</span>. Your setup for this client is complete!</li>
                            </ol>
                        </div>
                    </div>

                    {{-- Step 2 Details: Front-Desk Staff Link --}}
                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-header bg-white py-2 px-3 fw-bold small text-dark d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success">METHOD A</span> Handover for Hotel Front-Desk Staff (Zero Code / No Login)
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 rounded-pill" onclick="navigator.clipboard.writeText('Hello Reception Team,\n\nHere is your private Hotel WiFi Reception Console link:\n{{ route('hospitality.client-console', ['token' => $portal_token]) }}\n\nPlease bookmark this on your reception computer or tablet. You can click + Issue WiFi Ticket to print guest vouchers or view active rooms anytime with zero login required!'); alert('WhatsApp Reception handover message copied to clipboard!');">
                                <i class="bi bi-whatsapp"></i> Copy WhatsApp Message
                            </button>
                        </div>
                        <div class="card-body p-3 small text-muted">
                            <p class="mb-2">Send the hotel owner or receptionist their dedicated <strong>Staff Console Link</strong> (found in Section 1 above):</p>
                            <div class="bg-light p-2 rounded border font-monospace text-dark mb-2" style="font-size: 11px;">
                                {{ route('hospitality.client-console', ['token' => $portal_token]) }}
                            </div>
                            <ul class="mb-0 ps-3">
                                <li>Receptionists can bookmark this on their front-desk PC, tablet, or phone.</li>
                                <li><strong>Zero login credentials needed</strong>: Authenticated automatically via the secure token.</li>
                                <li>They only see their guest WiFi controls—no access to your ISP backend, routers, or billing secrets.</li>
                                <li>Includes live active guest counters, 1-click receipt slip reprints, and check-out revocation.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Step 3 Details: External POS / Opera PMS Developer Handover --}}
                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-header bg-white py-2 px-3 fw-bold small text-dark d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark">METHOD B</span> Handover to External POS / Opera PMS Developers (REST API)
                            </div>
                            <button type="button" class="btn btn-outline-dark btn-sm py-0 px-2 rounded-pill" onclick="navigator.clipboard.writeText('Hello Developer,\n\nHere are your HSmart WiFi Hotspot API credentials:\nAPI Endpoint: {{ url('/api/v1/hospitality/ticket') }}\nStats Endpoint: {{ url('/api/v1/hospitality/stats') }}\nX-API-Key: {{ $api_key }}\nMethod: POST\n\nWhen a guest checks in or receipt is printed, send a POST request with guest_name, room_number/receipt_ref, and nights/bill_amount. The API returns code, qr_url, and printable_text ready for your thermal receipt printer!'); alert('Developer handover brief copied to clipboard!');">
                                <i class="bi bi-clipboard"></i> Copy Developer Brief
                            </button>
                        </div>
                        <div class="card-body p-3 small text-muted">
                            <p class="mb-2">If the client uses <strong>Opera PMS, Cloudbeds, Loyverse, Sirvoy, or custom POS</strong>, give their developer these credentials:</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-2" style="font-size: 11px;">
                                    <tbody>
                                        <tr>
                                            <td class="fw-bold bg-light" style="width: 160px;">API Ticket Endpoint</td>
                                            <td class="font-monospace"><code>POST {{ url('/api/v1/hospitality/ticket') }}</code></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold bg-light">Live Stats Endpoint</td>
                                            <td class="font-monospace"><code>GET {{ url('/api/v1/hospitality/stats') }}</code></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold bg-light">Required Header</td>
                                            <td class="font-monospace"><code>X-API-Key: {{ $api_key }}</code></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold bg-light">Sample Payload</td>
                                            <td class="font-monospace"><code>{"ticket_type":"hotel_guest", "guest_name":"Mwangi", "room_number":"204", "nights":2, "device_limit":3}</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mb-0 text-dark">The API returns <code>code</code>, <code>qr_url</code>, and formatted <code>printable_text</code> that prints directly on 58mm/80mm thermal receipt slips.</p>
                        </div>
                    </div>

                    {{-- Step 4 Details: Endpoints Summary --}}
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white py-2 px-3 fw-bold small text-dark">
                            <i class="bi bi-plug-fill text-indigo me-1"></i> All 4 Built-in Endpoints Reference
                        </div>
                        <div class="card-body p-3 small">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <div class="fw-bold text-dark font-monospace small"><span class="badge bg-success me-1">POST</span> /api/v1/hospitality/ticket</div>
                                        <small class="text-muted">Generates a new WiFi ticket on bill print or room check-in.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <div class="fw-bold text-dark font-monospace small"><span class="badge bg-primary me-1">GET</span> /api/v1/hospitality/stats</div>
                                        <small class="text-muted">Pulls live active guests, today's counts, and active rooms.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <div class="fw-bold text-dark font-monospace small"><span class="badge bg-success me-1">POST</span> /api/v1/hospitality/verify-guest</div>
                                        <small class="text-muted">Verifies Room # + Guest Name for captive portal splash logins.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <div class="fw-bold text-dark font-monospace small"><span class="badge bg-danger me-1">POST</span> /api/v1/hospitality/ticket/{code}/revoke</div>
                                        <small class="text-muted">Revokes ticket & force-disconnects guest upon early checkout.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between">
                    <small class="text-muted">HSmart Hospitality Architecture • All sessions synced directly with MikroTik RouterOS</small>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close Manual</button>
                </div>
            </div>
        </div>
    </div>


</div>

