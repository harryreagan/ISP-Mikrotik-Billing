<div class="container-fluid py-4" style="max-width: 900px;" @if($step === 2) wire:poll.3s="checkRouterOnline" @endif>
    {{-- Page Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Add Router</h3>
            <p class="text-muted small mb-0">Connect and set up any MikroTik router in 4 simple steps.</p>
        </div>
        <a href="{{ route('mikrotik-sync') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>Cancel
        </a>
    </div>

    {{-- 4-Step Progress Bar --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="row text-center g-2">
                {{-- Step 1 --}}
                <div class="col-3">
                    <div class="d-flex flex-column align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 {{ $step >= 1 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 38px; height: 38px;">
                            @if($step > 1) <i class="bi bi-check-lg fs-5"></i> @else 1 @endif
                        </div>
                        <span class="small fw-semibold {{ $step === 1 ? 'text-primary' : ($step > 1 ? 'text-dark' : 'text-muted') }}">Name</span>
                    </div>
                </div>
                {{-- Step 2 --}}
                <div class="col-3">
                    <div class="d-flex flex-column align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 {{ $step >= 2 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 38px; height: 38px;">
                            @if($step > 2) <i class="bi bi-check-lg fs-5"></i> @else 2 @endif
                        </div>
                        <span class="small fw-semibold {{ $step === 2 ? 'text-primary' : ($step > 2 ? 'text-dark' : 'text-muted') }}">Connect</span>
                    </div>
                </div>
                {{-- Step 3 --}}
                <div class="col-3">
                    <div class="d-flex flex-column align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 {{ $step >= 3 ? 'bg-primary text-white' : 'bg-light text-muted border' }}" style="width: 38px; height: 38px;">
                            @if($step > 3) <i class="bi bi-check-lg fs-5"></i> @else 3 @endif
                        </div>
                        <span class="small fw-semibold {{ $step === 3 ? 'text-primary' : ($step > 3 ? 'text-dark' : 'text-muted') }}">Ports</span>
                    </div>
                </div>
                {{-- Step 4 --}}
                <div class="col-3">
                    <div class="d-flex flex-column align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 {{ $step === 4 ? ($is_configured ? 'bg-success text-white' : 'bg-primary text-white') : 'bg-light text-muted border' }}" style="width: 38px; height: 38px;">
                            @if($is_configured) <i class="bi bi-check-all fs-4"></i> @else 4 @endif
                        </div>
                        <span class="small fw-semibold {{ $step === 4 ? ($is_configured ? 'text-success' : 'text-primary') : 'text-muted' }}">Done</span>
                    </div>
                </div>
            </div>
            {{-- Progress Line --}}
            <div class="progress mt-2" style="height: 4px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ ($step - 1) * 33.33 + ($is_configured ? 33.34 : 10) }}%"></div>
            </div>
        </div>
    </div>

    {{-- ============================================================== --}}
    {{-- STEP 1 - NAME & LOCATION                                       --}}
    {{-- ============================================================== --}}
    @if($step === 1)
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-router fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-dark">Name Your Router</h4>
                    <p class="text-muted small">Give your router a friendly name and the building or town where it is installed.</p>
                </div>

                <form wire:submit.prevent="generateSetupScript">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Router Name <span class="text-danger">*</span></label>
                        <input type="text" wire:model.live="router_name" class="form-control form-control-lg @error('router_name') is-invalid @enderror" placeholder="e.g. Main Gateway or Westlands Hub" autofocus required>
                        @error('router_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Location / Branch <span class="text-danger">*</span></label>
                        <input type="text" wire:model.live="location" class="form-control form-control-lg @error('location') is-invalid @enderror" placeholder="e.g. CBD Plaza 2nd Floor or Nakuru Town" required>
                        @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">This helps you easily identify where this device is deployed.</div>
                    </div>

                    <button type="submit" 
                            class="btn btn-primary btn-lg w-100 py-3 fw-bold" 
                            @if(empty($router_name) || empty($location)) disabled @endif>
                        <span>Next: Generate Setup Script</span>
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
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
                    <p class="text-muted small">Follow these 3 simple instructions to connect your router to the platform.</p>
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

                {{-- Script Box with Copy Button --}}
                <div class="position-relative mb-4">
                    <div class="d-flex align-items-center justify-content-between bg-dark text-white px-3 py-2 rounded-top">
                        <span class="small font-monospace text-warning"><i class="bi bi-code-slash me-2"></i>MikroTik RouterOS Script</span>
                        <button type="button" 
                                id="copyScriptBtn"
                                class="btn btn-warning btn-sm fw-bold"
                                onclick="copyScriptCode()">
                            <i class="bi bi-clipboard me-1" id="copyIcon"></i>
                            <span id="copyText">Copy Script</span>
                        </button>
                    </div>
                    <textarea id="routerScriptArea" 
                              class="form-control font-monospace bg-dark text-white border-0 rounded-bottom p-3" 
                              rows="9" 
                              readonly 
                              style="font-size: 13px; resize: none; border-top-left-radius: 0 !important; border-top-right-radius: 0 !important;">{{ $script }}</textarea>
                </div>

                {{-- Listening Spinner --}}
                <div class="card border border-primary bg-primary bg-opacity-10 text-center p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <div class="spinner-border text-primary me-3" role="status">
                            <span class="visually-hidden">Waiting...</span>
                        </div>
                        <h5 class="fw-bold text-primary mb-0">Waiting for your router to connect...</h5>
                    </div>
                    <p class="text-muted small mb-0">
                        Once you paste the script in Winbox and press Enter, your router will automatically appear here within seconds.
                    </p>
                </div>

                <div class="text-center">
                    <button type="button" wire:click="advanceToStep3" class="btn btn-outline-secondary btn-sm">
                        Router already pasted? Continue to Ports <i class="bi bi-arrow-right ms-1"></i>
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
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                        <div>{{ $port_validation_error }}</div>
                    </div>
                @endif

                @if($is_loading_interfaces)
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-2"></div>
                        <p class="text-muted small">Reading ports from router...</p>
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
                                <strong>Tip:</strong> Plug your main internet cable from your provider (ISP) into <strong>{{ array_search('wan', $port_roles) ?: 'ether1' }}</strong>. Your customers or Access Points plug into the Hotspot/PPPoE ports.
                            </div>
                        </div>
                    </div>

                    <button type="button" 
                            wire:click="applyConfiguration" 
                            class="btn btn-primary btn-lg w-100 py-3 fw-bold"
                            @if($port_validation_error) disabled @endif>
                        <span>Apply &amp; Configure Router</span>
                        <i class="bi bi-arrow-right ms-2"></i>
                    </button>
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
                        <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
                        <h4 class="fw-bold text-dark">Applying Configuration</h4>
                        <p class="text-muted small">Please wait while we automatically set up your MikroTik router...</p>
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
                @else
                    {{-- Big Green Finished Screen --}}
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

                @if($apply_error)
                    <div class="alert alert-danger mt-4 text-start">
                        <strong>Configuration notice:</strong> {{ $apply_error }}
                        <div class="mt-2">
                            <button type="button" wire:click="goBackToStep3" class="btn btn-danger btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Go back to Step 3
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
function copyScriptCode() {
    const copyText = document.getElementById("routerScriptArea");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(() => {
        const btnText = document.getElementById("copyText");
        const icon = document.getElementById("copyIcon");
        btnText.innerText = "Copied to Clipboard!";
        icon.className = "bi bi-check-lg me-1";
        setTimeout(() => {
            btnText.innerText = "Copy Script";
            icon.className = "bi bi-clipboard me-1";
        }, 3000);
    });
}
</script>
