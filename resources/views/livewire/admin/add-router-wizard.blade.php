<div class="mx-auto" style="max-width: 900px;" @if($step === 2) wire:poll.3s="checkRouterOnline" @endif>
    {{-- Page Header --}}
    <x-ui.page-header title="Add router" subtitle="Connect and set up any MikroTik router in 4 verified steps.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('mikrotik-sync')">
                <i class="bi bi-x-lg"></i> Cancel
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- 4-Step Progress Bar --}}
    <x-ui.card class="mb-4 p-3">
        <div class="row text-center g-2">
            {{-- Step 1 --}}
            <div class="col-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold mb-1 {{ $step >= 1 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 34px; height: 34px; font-size: 0.75rem;">
                        @if($step > 1) <i class="bi bi-check-lg"></i> @else 1 @endif
                    </div>
                    <span class="small fw-medium {{ $step === 1 ? 'text-orange-600' : ($step > 1 ? 'text-dark' : 'text-muted') }}" style="font-size: 0.75rem;">Name</span>
                </div>
            </div>
            {{-- Step 2 --}}
            <div class="col-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold mb-1 {{ $step >= 2 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 34px; height: 34px; font-size: 0.75rem;">
                        @if($step > 2) <i class="bi bi-check-lg"></i> @else 2 @endif
                    </div>
                    <span class="small fw-medium {{ $step === 2 ? 'text-orange-600' : ($step > 2 ? 'text-dark' : 'text-muted') }}" style="font-size: 0.75rem;">Connect</span>
                </div>
            </div>
            {{-- Step 3 --}}
            <div class="col-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold mb-1 {{ $step >= 3 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 34px; height: 34px; font-size: 0.75rem;">
                        @if($step > 3) <i class="bi bi-check-lg"></i> @else 3 @endif
                    </div>
                    <span class="small fw-medium {{ $step === 3 ? 'text-orange-600' : ($step > 3 ? 'text-dark' : 'text-muted') }}" style="font-size: 0.75rem;">Ports</span>
                </div>
            </div>
            {{-- Step 4 --}}
            <div class="col-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-semibold mb-1 {{ $step === 4 ? ($is_configured ? 'bg-success text-white' : ($apply_error ? 'bg-danger text-white' : 'bg-primary text-white')) : 'bg-light text-muted border' }}" style="width: 34px; height: 34px; font-size: 0.75rem;">
                        @if($is_configured) <i class="bi bi-check-all"></i> @elseif($apply_error) <i class="bi bi-exclamation-triangle"></i> @else 4 @endif
                    </div>
                    <span class="small fw-medium {{ $step === 4 ? ($is_configured ? 'text-success' : ($apply_error ? 'text-danger' : 'text-orange-600')) : 'text-muted' }}" style="font-size: 0.75rem;">Done</span>
                </div>
            </div>
        </div>
        {{-- Progress Line --}}
        <div class="progress mt-2.5" style="height: 4px;">
            <div class="progress-bar {{ $apply_error ? 'bg-danger' : ($is_configured ? 'bg-success' : 'bg-primary') }}" role="progressbar" style="width: {{ ($step - 1) * 33.33 + ($is_configured ? 33.34 : 10) }}%"></div>
        </div>
    </x-ui.card>

    {{-- ============================================================== --}}
    {{-- STEP 1 - NAME & LOCATION                                       --}}
    {{-- ============================================================== --}}
    @if($step === 1)
        <x-ui.card>
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-orange-50 text-orange-600 rounded-circle mb-3" style="width: 48px; height: 48px;">
                    <i class="bi bi-router fs-4"></i>
                </div>
                <h4 class="ui-section-title">Name your router</h4>
                <p class="text-muted small mb-0">Give your router a friendly name and the building or town where it is installed.</p>
            </div>

            <form wire:submit.prevent="generateSetupScript">
                <div class="mb-3">
                    <label class="form-label">Router name <span class="text-danger">*</span></label>
                    <input type="text" wire:model.live="router_name" class="form-control @error('router_name') is-invalid @enderror" placeholder="e.g. Main Gateway or Westlands Hub" autofocus required>
                    @error('router_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">Location / branch <span class="text-danger">*</span></label>
                    <input type="text" wire:model.live="location" class="form-control @error('location') is-invalid @enderror" placeholder="e.g. CBD Plaza 2nd Floor or Nakuru Town" required>
                    @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">This helps you easily identify where this device is deployed.</div>
                </div>

                <button type="submit" 
                        class="btn btn-primary w-100 py-2.5" 
                        @if(empty($router_name) || empty($location)) disabled @endif>
                    <span>Next: generate setup script</span>
                    <i class="bi bi-arrow-right ms-1.5"></i>
                </button>
            </form>
        </x-ui.card>
    @endif

    {{-- ============================================================== --}}
    {{-- STEP 2 - CONNECT VIA ROUTEROS TERMINAL                         --}}
    {{-- ============================================================== --}}
    @if($step === 2)
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-terminal fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Connect Your Router</h4>
                    <p class="text-muted small">Follow these 3 simple instructions to connect your MikroTik router to HSmart Cloud.</p>
                </div>

                {{-- 3 Plain Instructions --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100 text-center p-3">
                            <div class="fw-bold text-primary mb-1 fs-5">Step 1</div>
                            <div class="fw-semibold text-dark">Open Winbox</div>
                            <small class="text-muted">Connect to your MikroTik router from your PC.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100 text-center p-3">
                            <div class="fw-bold text-primary mb-1 fs-5">Step 2</div>
                            <div class="fw-semibold text-dark">Click New Terminal</div>
                            <small class="text-muted">Open the black terminal window from the left menu.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-0 h-100 text-center p-3">
                            <div class="fw-bold text-primary mb-1 fs-5">Step 3</div>
                            <div class="fw-semibold text-dark">Paste &amp; Press Enter</div>
                            <small class="text-muted">Paste the script below and hit Enter on your keyboard.</small>
                        </div>
                    </div>
                </div>

                {{-- One-Line Terminal Command (Industry Standard Bootstrap) --}}
                <div class="position-relative mb-4">
                    <div class="d-flex align-items-center justify-content-between bg-dark text-white px-3 py-2 rounded-top">
                        <span class="small font-monospace text-warning">
                            <i class="bi bi-terminal me-2"></i>One-Line MikroTik Terminal Command
                        </span>
                        <button type="button" 
                                id="copyCmdBtn"
                                class="btn btn-warning btn-sm fw-bold px-3 shadow-sm"
                                onclick="copyBootstrapCommand()">
                            <i class="bi bi-clipboard me-1" id="copyCmdIcon"></i>
                            <span id="copyCmdText">Copy Command</span>
                        </button>
                    </div>
                    <textarea id="routerBootstrapArea" 
                              class="form-control font-monospace bg-dark text-white border-0 rounded-bottom p-3" 
                              rows="3" 
                              readonly 
                              style="font-size: 13px; line-height: 1.6; resize: none; border-top-left-radius: 0 !important; border-top-right-radius: 0 !important; color: #38bdf8 !important;">{{ $one_line_command }}</textarea>
                </div>

                {{-- Collapsible Raw Script Details --}}
                <div class="accordion mb-4" id="scriptAccordion">
                    <div class="accordion-item border rounded-3 overflow-hidden">
                        <h2 class="accordion-header" id="headingScript">
                            <button class="accordion-button collapsed py-2 px-3 bg-light text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#collapseScript">
                                <i class="bi bi-code-slash me-2 text-secondary"></i>Advanced: View Full RouterOS Configuration Script
                            </button>
                        </h2>
                        <div id="collapseScript" class="accordion-collapse collapse" data-bs-parent="#scriptAccordion">
                            <div class="accordion-body p-0">
                                <textarea id="routerScriptArea" 
                                          class="form-control font-monospace bg-dark text-light border-0 p-3" 
                                          rows="8" 
                                          readonly 
                                          style="font-size: 12px; resize: none;">{{ $script }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live Connection Polling Status with Premium Glassmorphism & Blur --}}
                <div class="card mb-4 overflow-hidden border-0 shadow-sm" 
                     style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1.5px solid rgba(244, 123, 6, 0.28) !important; border-radius: 18px;">
                    <div class="card-body p-4 text-center">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="position-relative me-3" style="width: 46px; height: 46px;">
                                <span class="position-absolute top-0 start-0 w-100 h-100 rounded-circle bg-warning opacity-25 animate-ping"></span>
                                <div class="w-100 h-100 rounded-circle d-flex align-items-center justify-content-center" style="background: rgba(244, 123, 6, 0.15); border: 2px solid #F47B06;">
                                    <i class="bi bi-broadcast fs-5" style="color: #F47B06;"></i>
                                </div>
                            </div>
                            <div class="text-start">
                                <h5 class="fw-bold text-dark mb-0" style="letter-spacing: -0.2px;">Waiting for Router Connection...</h5>
                                <small class="text-muted">Listening for live heartbeat over WireGuard tunnel</small>
                            </div>
                        </div>

                        <p class="text-secondary small mb-3 mx-auto" style="max-width: 580px; line-height: 1.6;">
                            Once you paste the command into Winbox Terminal and press <kbd class="bg-dark text-white px-2 py-0.5 rounded" style="font-size: 11px;">Enter</kbd>, your router will download the script, activate the tunnel, and this page will advance automatically.
                        </p>

                        <div class="d-flex flex-wrap justify-content-center gap-2 mt-2">
                            <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill bg-white border border-light-subtle shadow-2xs">
                                <i class="bi bi-shield-lock-fill text-warning fs-9"></i>
                                <span class="text-muted fs-11">Token:</span>
                                <span class="font-monospace fw-bold text-dark fs-11">{{ $setup_token }}</span>
                            </div>
                            <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill bg-white border border-light-subtle shadow-2xs">
                                <i class="bi bi-diagram-3-fill fs-9" style="color: #6D3FE0;"></i>
                                <span class="text-muted fs-11">Gateway Tunnel IP:</span>
                                <span class="font-monospace fw-bold text-dark fs-11">{{ $tunnel_ip }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($check_message)
                    <div class="alert alert-warning text-start mb-4 d-flex align-items-start border-0 shadow-sm" style="border-radius: 14px; background: #FFFBEB; border: 1px solid #FDE68A !important;">
                        <i class="bi bi-info-circle-fill fs-5 text-warning me-2 mt-0.5"></i>
                        <div class="text-dark small">{{ $check_message }}</div>
                    </div>
                @endif

                <div class="d-flex justify-content-center gap-2">
                    <button type="button" wire:click="manualCheckConnection" class="btn btn-primary px-4 py-2.5 rounded-pill shadow-sm fw-bold">
                        <i class="bi bi-arrow-repeat me-1.5"></i>Check Connection Now
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- STEP 3 - PORTS CONFIGURATION                                   --}}
    {{-- ============================================================== --}}
    @if($step === 3)
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-ethernet fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Assign Port Roles</h4>
                    <p class="text-muted small">Choose how each physical cable port on <strong>{{ $router_name }}</strong> will be used.</p>
                </div>

                @if($port_validation_error)
                    <div class="alert alert-danger mb-4 text-start" role="alert">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 mt-1"></i>
                            <div>
                                <div class="fw-bold mb-1">Port Detection Notice</div>
                                <div>{{ $port_validation_error }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($is_loading_interfaces)
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-2"></div>
                        <p class="text-muted small">Querying network interfaces directly from router...</p>
                    </div>
                @elseif(empty($interfaces))
                    {{-- Honest error state: No fake ports! --}}
                    <div class="card bg-light border-0 p-4 text-center mb-4">
                        <i class="bi bi-hdd-network text-warning fs-1 mb-2"></i>
                        <h5 class="fw-bold text-dark">No Hardware Interfaces Found</h5>
                        <p class="text-muted small mb-4" style="max-width: 500px; margin: 0 auto;">
                            We could not read ports from your router via RouterOS API. Please make sure the setup script ran completely in Winbox Terminal, the WireGuard tunnel is active, and the router has internet access.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" wire:click="fetchInterfaces" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry Reading Ports
                            </button>
                            <button type="button" wire:click="goBackToStep2" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Script (Step 2)
                            </button>
                        </div>
                    </div>
                @else
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Port Name</th>
                                    <th style="width: 30%;">Cable Status</th>
                                    <th style="width: 45%;">Role Assignment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($interfaces as $iface)
                                    @php $portName = $iface['name']; @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-hdd-network text-primary fs-5 me-2"></i>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $portName }}</div>
                                                    <small class="text-muted">{{ $iface['mac'] ?? '' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($iface['running'])
                                                <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 py-2 px-3">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Cable plugged in
                                                </span>
                                            @else
                                                <span class="badge bg-light text-muted border py-2 px-3">
                                                    <i class="bi bi-dash-circle me-1"></i>No cable
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <select wire:model.live="port_roles.{{ $portName }}" class="form-select form-select-lg">
                                                <option value="wan">🌐 Internet (WAN)</option>
                                                <option value="hotspot">📶 Hotspot</option>
                                                <option value="pppoe">🔌 PPPoE</option>
                                                <option value="none">⚪ Not used</option>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-4">
                        <div class="d-flex align-items-center small text-muted">
                            <i class="bi bi-info-circle text-primary me-2 fs-5"></i>
                            <div>
                                <strong>Tip:</strong> Plug your main internet cable from your provider into <strong>{{ array_search('wan', $port_roles) ?: 'ether1' }}</strong>. Your customers or Access Points plug into the Hotspot/PPPoE ports.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" wire:click="goBackToStep2" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Script
                        </button>
                        <button type="button" 
                                wire:click="applyConfiguration" 
                                class="btn btn-primary btn-lg px-4 py-3 fw-bold"
                                @if($port_validation_error || empty($interfaces)) disabled @endif>
                            <span>Apply &amp; Configure Router</span>
                            <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ============================================================== --}}
    {{-- STEP 4 - DONE & CHECKLIST                                      --}}
    {{-- ============================================================== --}}
    @if($step === 4)
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                @if(!$is_configured)
                    <div class="text-center mb-4">
                        @if($apply_error)
                            <div class="d-inline-flex align-items-center justify-content-center bg-danger text-white rounded-circle mb-3" style="width: 70px; height: 70px;">
                                <i class="bi bi-x-lg fs-1"></i>
                            </div>
                            <h4 class="fw-bold text-danger">Configuration Incomplete</h4>
                            <p class="text-muted small">We could not complete the setup on your router. See details below.</p>
                        @else
                            <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
                            <h4 class="fw-bold text-dark">Applying Configuration</h4>
                            <p class="text-muted small">Please wait while we automatically configure your MikroTik router...</p>
                        @endif
                    </div>

                    {{-- Live Animated Checklist --}}
                    <div class="list-group list-group-flush mb-4">
                        @foreach($checklist as $key => $item)
                            <div class="list-group-item d-flex align-items-center py-3">
                                @if($item['status'] === 'done')
                                    <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                                    <span class="fw-semibold text-dark">{{ $item['label'] }}</span>
                                @elseif($item['status'] === 'failed')
                                    <i class="bi bi-x-circle-fill text-danger fs-4 me-3"></i>
                                    <span class="fw-semibold text-danger">{{ $item['label'] }}</span>
                                @else
                                    <div class="spinner-border spinner-border-sm text-primary me-3"></div>
                                    <span class="text-muted">{{ $item['label'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($apply_error)
                        <div class="alert alert-danger mb-4 text-start">
                            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Error Details:</div>
                            <div class="small font-monospace">{{ $apply_error }}</div>
                        </div>

                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" wire:click="applyConfiguration" class="btn btn-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry Configuration
                            </button>
                            <button type="button" wire:click="goBackToStep3" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Port Selection
                            </button>
                        </div>
                    @endif
                @else
                    {{-- Big Green Finished Screen (Shown ONLY after all checks succeed) --}}
                    <div class="text-center py-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle shadow-lg mb-4" style="width: 90px; height: 90px;">
                            <i class="bi bi-check-lg" style="font-size: 50px;"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">Router is Ready!</h2>
                        <p class="text-muted mb-4 fs-6">
                            <strong>{{ $router_name }}</strong> is now online, securely connected via WireGuard tunnel, and fully configured for billing.
                        </p>

                        {{-- Verified Checklist Badges --}}
                        <div class="card bg-light border-0 p-3 mb-4 mx-auto" style="max-width: 550px;">
                            <div class="row g-2 text-start small">
                                <div class="col-6"><i class="bi bi-check2 text-success me-1"></i>Internet (WAN) DHCP Ready</div>
                                <div class="col-6"><i class="bi bi-check2 text-success me-1"></i>Secure Firewall NAT Active</div>
                                <div class="col-6"><i class="bi bi-check2 text-success me-1"></i>Hotspot 10.10.0.1 Subnet Up</div>
                                <div class="col-6"><i class="bi bi-check2 text-success me-1"></i>PPPoE Server Operational</div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="row g-3 justify-content-center" style="max-width: 550px; margin: 0 auto;">
                            <div class="col-sm-6">
                                <a href="{{ route('mikrotik-sync') }}" class="btn btn-outline-secondary btn-lg w-100 py-3 fw-bold">
                                    <i class="bi bi-router me-2"></i>Go to Routers
                                </a>
                            </div>
                            <div class="col-sm-6">
                                <a href="{{ route('admin.packages') }}" class="btn btn-primary btn-lg w-100 py-3 fw-bold">
                                    <i class="bi bi-plus-circle me-2"></i>Add First Package
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
function copyBootstrapCommand() {
    const copyText = document.getElementById("routerBootstrapArea");
    if (!copyText) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    const feedback = () => {
        const btnText = document.getElementById("copyCmdText");
        const icon = document.getElementById("copyCmdIcon");
        if (btnText && icon) {
            btnText.innerText = "Copied to Clipboard!";
            icon.className = "bi bi-check2-circle me-1";
            setTimeout(() => {
                btnText.innerText = "Copy Command";
                icon.className = "bi bi-clipboard me-1";
            }, 3000);
        }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(copyText.value).then(feedback).catch(() => {
            document.execCommand('copy');
            feedback();
        });
    } else {
        document.execCommand('copy');
        feedback();
    }
}

function copyScriptCode() {
    const copyText = document.getElementById("routerScriptArea");
    if (!copyText) return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(() => {
        const btnText = document.getElementById("copyText");
        const icon = document.getElementById("copyIcon");
        if (btnText && icon) {
            btnText.innerText = "Copied to Clipboard!";
            icon.className = "bi bi-check-lg me-1";
            setTimeout(() => {
                btnText.innerText = "Copy Script";
                icon.className = "bi bi-clipboard me-1";
            }, 3000);
        }
    });
}
</script>
