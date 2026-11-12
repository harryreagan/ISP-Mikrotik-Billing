<div>
    {{-- Header --}}
    <x-ui.page-header
        title="Captive portal brand customizer"
        subtitle="Select from 8 crafted designs, customize your branding, and deploy directly to your MikroTik routers."
    >
        <x-slot:actions>
            <button type="button" wire:click="saveSettings" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Portal Settings
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ============================================================== --}}
    {{-- CHOOSE A DESIGN GALLERY (ABOVE BRANDING)                      --}}
    {{-- ============================================================== --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="ui-section-title mb-0"><i class="bi bi-grid-fill text-orange-500 me-2"></i>Choose a Design</h5>
                <small class="text-muted">Select any of the 8 responsive layouts. All your branding and packages automatically adapt.</small>
            </div>
            <span class="badge bg-primary">Active: {{ ucfirst($template) }}</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @foreach($templatesCatalog as $tKey => $meta)
                    @php $isActive = ($template === $tKey); @endphp
                    <div class="col-6 col-md-3">
                        <div class="card h-100 text-start cursor-pointer position-relative transition-all {{ $isActive ? 'ring-2 ring-orange-500' : 'hover:bg-slate-50' }}"
                             style="cursor: pointer; border-radius: 14px; overflow: hidden;"
                             wire:click="setTemplate('{{ $tKey }}')">
                            
                            {{-- Mini Visual Gradient Thumbnail --}}
                            <div style="height: 80px; background: {{ $meta['gradient'] }}; display: flex; align-items: center; justify-content: center; position: relative;">
                                @if($isActive)
                                    <div class="position-absolute top-0 end-0 m-2 badge bg-primary rounded-circle p-1">
                                        <i class="bi bi-check-lg text-white fs-6"></i>
                                    </div>
                                @endif
                                <div class="bg-white bg-opacity-20 backdrop-blur px-3 py-1 rounded-pill text-white fw-semibold small">
                                    {{ $meta['name'] }}
                                </div>
                            </div>

                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <h6 class="text-sm font-semibold mb-0 text-slate-800">{{ $meta['name'] }}</h6>
                                    @if($isActive)
                                        <span class="badge bg-success">Selected</span>
                                    @endif
                                </div>
                                <p class="small text-muted mb-0" style="font-size: 11px; line-height: 1.4;">{{ $meta['vibe'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Settings Controls (Left Column) --}}
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="ui-section-title mb-0"><i class="bi bi-brush text-orange-500 me-2"></i>Branding &amp; Visual Identity</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Brand Name</label>
                            <input type="text" wire:model.live.debounce.400ms="brand_name" class="form-control" placeholder="e.g. StarNet WiFi">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Primary Color</label>
                            <div class="d-flex gap-2">
                                <input type="color" wire:model.live.debounce.400ms="brand_color" class="form-control form-control-color">
                                <input type="text" wire:model.live.debounce.400ms="brand_color" class="form-control font-monospace" style="width: 90px;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Secondary Color</label>
                            <div class="d-flex gap-2">
                                <input type="color" wire:model.live.debounce.400ms="secondary_color" class="form-control form-control-color">
                                <input type="text" wire:model.live.debounce.400ms="secondary_color" class="form-control font-monospace" style="width: 90px;">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Logo Image URL</label>
                            <input type="text" wire:model.live.debounce.400ms="logo_url" class="form-control" placeholder="https://yourdomain.com/logo.png">
                            <small class="text-muted">Direct HTTPS image link</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Background Wallpaper URL</label>
                            <input type="text" wire:model.live.debounce.400ms="background_url" class="form-control" placeholder="https://yourdomain.com/bg.jpg">
                            <small class="text-muted">Optional custom background</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Main Headline</label>
                            <input type="text" wire:model.live.debounce.400ms="headline" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subheadline / Value Proposition</label>
                            <input type="text" wire:model.live.debounce.400ms="subheadline" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Support Phone</label>
                            <input type="text" wire:model.live.debounce.400ms="support_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp Direct Link Number</label>
                            <input type="text" wire:model.live.debounce.400ms="support_whatsapp" class="form-control" placeholder="2547XXXXXXXX">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Payment Instructions</label>
                            <textarea wire:model.live.debounce.400ms="payment_instructions" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Terms of Service Notice</label>
                            <textarea wire:model.live.debounce.400ms="terms_text" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 1-Click Router Deploy Card --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="ui-section-title mb-0"><i class="bi bi-hdd-network text-orange-500 me-2"></i>Install to Router &amp; Walled Garden</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Walled Garden Whitelisted Domains (Comma separated)</label>
                        <input type="text" wire:model="walled_garden_domains" class="form-control font-monospace">
                        <small class="text-muted">Allows clients to reach M-Pesa STK push and payment gateways before buying a package.</small>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label class="form-label">Target Router</label>
                            <select wire:model="selected_router_id" class="form-select">
                                <option value="">-- Choose Destination Router --</option>
                                @foreach($routers as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->ip_address ?: $r->vpn_ip }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <button type="button" wire:click="installToRouter" class="btn btn-primary w-100">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i>Install to Router
                            </button>
                        </div>
                    </div>

                    @if($install_status)
                        <div class="alert {{ $install_status['status'] === 'success' ? 'alert-success' : 'alert-info' }} mt-3 mb-0">
                            {{ $install_status['message'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Live Mockup Preview (Right Column - Dynamic Iframe) --}}
        <div class="col-lg-6">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="ui-section-title mb-0"><i class="bi bi-eye text-orange-500 me-2"></i>Live Portal Mockup</h5>
                        <small class="text-muted">Design: <strong>{{ ucfirst($template) }}</strong> (Fully responsive)</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" 
                                wire:click="$set('preview_device', 'mobile')" 
                                class="btn {{ $preview_device === 'mobile' ? 'btn-primary' : 'btn-secondary' }}">
                            <i class="bi bi-phone me-1"></i>Mobile (375px)
                        </button>
                        <button type="button" 
                                wire:click="$set('preview_device', 'desktop')" 
                                class="btn {{ $preview_device === 'desktop' ? 'btn-primary' : 'btn-secondary' }}">
                            <i class="bi bi-display me-1"></i>Desktop
                        </button>
                    </div>
                </div>
                <div class="card-body p-3 bg-slate-50 d-flex justify-content-center align-items-center" style="min-height: 720px;">
                    {{-- Interactive Iframe with srcdoc --}}
                    <div class="rounded-2xl ring-1 ring-slate-200/70 overflow-hidden bg-white d-flex justify-content-center transition-all" 
                         style="{{ $preview_device === 'mobile' ? 'width: 375px; height: 680px;' : 'width: 100%; height: 680px;' }}; transition: width 0.3s ease;">
                        <iframe id="portalPreviewIframe"
                                srcdoc="{{ $previewHtml }}" 
                                class="w-100 h-100 border-0" 
                                sandbox="allow-scripts allow-forms allow-same-origin"
                                title="Captive Portal Live Preview"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
