<div>
    {{-- ── Header ── --}}
    <nav class="hospitality-navbar no-print">
        <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2 bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-hospital fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">{{ $businessName }}</h5>
                    <small class="text-muted"><i class="bi bi-wifi text-success me-1"></i> Front-Desk & POS WiFi Management Console</small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                    <i class="bi bi-circle-fill me-1 small"></i> System Connected
                </span>
                <button type="button" class="btn btn-hospitality-primary btn-sm rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#issueTicketModal">
                    <i class="bi bi-plus-circle me-1"></i> Issue WiFi Ticket
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">

        {{-- ── Flash Alerts ── --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ── KPI Cards ── --}}
        <div class="row g-3 mb-4 no-print">
            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Active Guests Now</span>
                        <h2 class="fw-bold mb-0 text-primary">{{ $activeCount }}</h2>
                        <small class="text-muted">Connected hotel & dining guests</small>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Tickets Issued Today</span>
                        <h2 class="fw-bold mb-0 text-success">{{ $todayCount }}</h2>
                        <small class="text-muted">Since midnight today</small>
                    </div>
                    <div class="rounded-circle bg-success-subtle text-success p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Occupied Rooms with WiFi</span>
                        <h2 class="fw-bold mb-0 text-dark">{{ $occupiedRooms }}</h2>
                        <small class="text-muted">Distinct active room numbers</small>
                    </div>
                    <div class="rounded-circle bg-warning-subtle text-warning p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-door-open-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Main Table Card ── --}}
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden no-print">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Active Guest Folios & Tickets</h6>
                    <small class="text-muted">Search by room number, guest name, or voucher code to reprint or manage.</small>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0" placeholder="Room #, Name, Code..." wire:model.live.debounce.300ms="search">
                    </div>
                    <select class="form-select form-select-sm" wire:model.live="typeFilter" style="width: 140px;">
                        <option value="all">All Types</option>
                        <option value="hotel_guest">Hotel Room</option>
                        <option value="pos_receipt">POS Receipt</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">WiFi Code</th>
                            <th>Type</th>
                            <th>Room / Ref</th>
                            <th>Guest Name</th>
                            <th>Duration</th>
                            <th>Devices</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th class="pe-4 text-end">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($tickets as $t)
                            <tr>
                                <td class="ps-4">
                                    <span class="font-mono fw-bold text-dark">{{ $t->ticket_code }}</span>
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
                                        <span class="fw-bold text-dark">Room {{ $t->room_number }}</span>
                                    @endif
                                    @if($t->receipt_ref)
                                        <span class="text-muted d-block small">{{ $t->receipt_ref }}</span>
                                    @endif
                                    @if(!$t->room_number && !$t->receipt_ref)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $t->guest_name ?: 'Guest' }}</span>
                                </td>
                                <td>
                                    {{ $t->duration_hours >= 24 ? (int)($t->duration_hours / 24) . ' Night(s)' : $t->duration_hours . ' Hour(s)' }}
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $t->device_limit }} Device(s)</span>
                                </td>
                                <td>
                                    @if($t->status === 'active')
                                        <span class="badge bg-success text-white">Active</span>
                                    @elseif($t->status === 'revoked')
                                        <span class="badge bg-danger text-white">Checked Out</span>
                                    @else
                                        <span class="badge bg-secondary text-white">{{ ucfirst($t->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-muted">
                                    {{ $t->expires_at ? $t->expires_at->format('M d, H:i') : '—' }}
                                </td>
                                <td class="pe-4 text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="reprintTicket({{ $t->id }})" class="btn btn-outline-secondary" title="Print Slip">
                                            <i class="bi bi-printer"></i> Slip
                                        </button>
                                        @if($t->status === 'active')
                                            <button wire:click="revokeTicket({{ $t->id }})" wire:confirm="Check out / revoke WiFi for {{ $t->ticket_code }}?" class="btn btn-outline-danger" title="Revoke">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-ticket-perforated fs-1 d-block mb-2 text-secondary"></i>
                                    No guest WiFi tickets issued yet. Click "+ Issue WiFi Ticket" above to issue the first voucher.
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

    </div>

    {{-- ── Issue Ticket Modal ── --}}
    <div wire:ignore.self class="modal fade no-print" id="issueTicketModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom py-3 px-4">
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-ticket-perforated text-primary"></i> Issue Guest WiFi Ticket
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit.prevent="issueTicket">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Service Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="ticket_type" id="type_hotel" value="hotel_guest" wire:model.live="ticket_type">
                                <label class="btn btn-outline-primary btn-sm py-2" for="type_hotel"><i class="bi bi-hospital me-1"></i> Hotel Room Stay</label>

                                <input type="radio" class="btn-check" name="ticket_type" id="type_pos" value="pos_receipt" wire:model.live="ticket_type">
                                <label class="btn btn-outline-primary btn-sm py-2" for="type_pos"><i class="bi bi-receipt me-1"></i> Restaurant / POS Bill</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Guest / Customer Name</label>
                            <input type="text" class="form-control" wire:model="guest_name" placeholder="e.g. John Mwangi" required>
                        </div>

                        @if($ticket_type === 'hotel_guest')
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Room Number</label>
                                    <input type="text" class="form-control font-mono" wire:model="room_number" placeholder="e.g. 204" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Stay Duration (Nights)</label>
                                    <input type="number" class="form-control" min="1" max="60" wire:model="nights" required>
                                </div>
                            </div>
                        @else
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Bill Total (KES)</label>
                                    <input type="number" step="10" class="form-control" min="0" wire:model="bill_amount" placeholder="e.g. 350">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-semibold small">Receipt #</label>
                                    <input type="text" class="form-control font-mono" wire:model="receipt_ref" placeholder="REC-4821">
                                </div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Permitted Devices</label>
                            <select class="form-select" wire:model="device_limit">
                                <option value="1">1 Device (Phones only)</option>
                                <option value="2">2 Devices (Phone + Laptop)</option>
                                <option value="3">3 Devices (Standard Room)</option>
                                <option value="5">5 Devices (Suite / Family Room)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-hospitality-primary rounded-pill px-4 shadow-sm" data-bs-dismiss="modal">
                            <i class="bi bi-printer me-1"></i> Issue & Print Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Slip Preview & Print Modal ── --}}
    @if($showSuccessModal && $activeTicket)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.55);" wire:click.self="closeSuccessModal">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
                <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                    <div class="modal-header bg-dark text-white py-3 px-4 no-print">
                        <h6 class="modal-title fw-bold mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i> WiFi Ticket Ready</h6>
                        <button type="button" class="btn-close btn-close-white" wire:click="closeSuccessModal"></button>
                    </div>
                    <div class="modal-body p-4 bg-light text-center">
                        {{-- Printable ESC/POS Receipt Container --}}
                        <div id="receiptSlipContent" class="bg-white p-3 rounded-3 shadow-sm font-monospace text-dark text-start mx-auto" style="font-size: 11px; max-width: 320px; border: 1px dashed #999;">
                            <div class="text-center pb-2 border-bottom">
                                <div class="fw-bold fs-6">{{ strtoupper($businessName) }}</div>
                                <div class="small text-muted">{{ $activeTicket['ticket_type'] === 'hotel_guest' ? 'OFFICIAL GUEST WI-FI PASS' : 'CUSTOMER WI-FI SLIP' }}</div>
                            </div>

                            <pre class="m-0 py-2" style="white-space: pre-wrap; font-family: monospace; font-size: 11px;">{{ $activeTicket['printable_text'] }}</pre>

                            @if(!empty($activeTicket['qr_url']))
                                <div class="text-center pt-2 border-top">
                                    <div class="small fw-bold text-muted mb-1">[ 1-CLICK AUTO-CONNECT ]</div>
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($activeTicket['qr_url']) }}" alt="WiFi QR" class="my-1 rounded border p-1" style="width: 100px; height: 100px;">
                                    <div class="small text-muted" style="font-size: 9px;">Scan with phone camera to connect</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between no-print">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" wire:click="closeSuccessModal">Done</button>
                        <button type="button" class="btn btn-dark rounded-pill px-4" onclick="window.print();">
                            <i class="bi bi-printer me-1"></i> Print Slip
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

