<?php

namespace App\Livewire\Admin;

use App\Models\PackageList;
use App\Models\Router;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class CaptivePortalCustomizer extends Component
{
    use WithFileUploads;

    // Design Template Choice (Default to clean HSmart SaaS design)
    public string $template = 'hsmart';

    // Branding & Identity
    public $brand_name = 'HSmart WiFi';
    public $brand_color = '#F57C00';
    public $secondary_color = '#0F172A';
    public $logo_url = '';
    public $logo_upload = null;
    public $background_url = '';
    public $headline = 'Welcome to High-Speed Internet';
    public $subheadline = 'Instant automated M-Pesa activation with zero buffering.';
    public $badge_text = '';
    public $show_badge = true;
    public $terms_text = 'By connecting, you agree to our fair use policy and terms of service.';
    public $support_phone = '';
    public $support_whatsapp = '';
    public $theme_layout = 'cards';
    public $free_trial_enabled = false;
    public $free_trial_duration = '15m';
    public $payment_instructions = 'Select a package, enter your M-Pesa phone number, and accept the STK push on your phone.';
    public $walled_garden_domains = 'safaricom.co.ke, daraja.safaricom.co.ke, payhero.co.ke, hsmart.co.ke';

    // Target router for 1-click install
    public $selected_router_id = null;
    public $install_status = null;
    public $save_status = null;
    public $preview_device = 'mobile'; // 'mobile' (375px) or 'desktop'

    public function mount()
    {
        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;
        $tenantObj = \App\Models\Tenant::find($tenantId);
        $tenantName = $tenantObj?->name ?: 'My WiFi';

        $settings = DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->first();
        if ($settings) {
            $this->template             = $settings->template ?: 'hsmart';
            $this->brand_name           = (!empty($settings->brand_name) && $settings->brand_name !== 'HSmart WiFi') ? $settings->brand_name : $tenantName;
            $this->brand_color          = $settings->brand_color ?: $this->brand_color;
            $this->secondary_color      = $settings->secondary_color ?: $this->secondary_color;
            $this->logo_url             = $settings->logo_url ?: '';
            $this->background_url       = $settings->background_url ?: '';
            $this->headline             = $settings->headline ?: $this->headline;
            $this->subheadline          = $settings->subheadline ?: $this->subheadline;
            $this->badge_text           = $settings->badge_text ?? '';
            $this->show_badge           = isset($settings->show_badge) ? (bool) $settings->show_badge : true;
            $this->terms_text           = $settings->terms_text ?: $this->terms_text;
            $this->support_phone        = ($settings->support_phone && !str_contains($settings->support_phone, '743 606 108') && !str_contains($settings->support_phone, '743606108')) ? $settings->support_phone : '';
            $this->support_whatsapp     = ($settings->support_whatsapp && !str_contains($settings->support_whatsapp, '743606108')) ? $settings->support_whatsapp : '';
            $this->theme_layout         = $settings->theme_layout ?: $this->theme_layout;
            $this->free_trial_enabled   = (bool) $settings->free_trial_enabled;
            $this->free_trial_duration  = $settings->free_trial_duration ?: '15m';
            $this->payment_instructions = $settings->payment_instructions ?: $this->payment_instructions;
            
            if (!empty($settings->walled_garden_domains)) {
                $domains = is_string($settings->walled_garden_domains) ? json_decode($settings->walled_garden_domains, true) : $settings->walled_garden_domains;
                $this->walled_garden_domains = is_array($domains) ? implode(', ', $domains) : $settings->walled_garden_domains;
            }
        }

        $firstRouter = Router::forTenant($tenantId)->first();
        if ($firstRouter) {
            $this->selected_router_id = $firstRouter->id;
        }
    }

    public function setTemplate(string $templateKey)
    {
        if (array_key_exists($templateKey, CaptivePortalTemplates::catalog())) {
            $this->template = $templateKey;
            $this->save_status = null;
        }
    }

    public function updatedLogoUpload()
    {
        $this->validate([
            'logo_upload' => 'image|max:2048', // 2MB max
        ]);

        $path = $this->logo_upload->store('portal-logos', 'public');
        $this->logo_url = asset('storage/' . $path);
        $this->saveSettings(false);
    }

    public function removeLogo()
    {
        $this->logo_url = '';
        $this->logo_upload = null;
        $this->saveSettings(false);
    }

    public function saveSettings(bool $pushToRouters = true)
    {
        $this->save_status = null;

        try {
            $validKeys = array_keys(CaptivePortalTemplates::catalog());
            $this->validate([
                'template' => 'required|string|in:' . implode(',', $validKeys),
            ]);

            $user = auth()->user();
            $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

            $domainsArray = array_values(array_filter(array_map('trim', explode(',', (string) $this->walled_garden_domains))));

            DB::table('isp_portal_settings')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'template'              => $this->template,
                    'brand_name'            => $this->brand_name,
                    'brand_color'           => $this->brand_color,
                    'secondary_color'       => $this->secondary_color,
                    'logo_url'              => $this->logo_url,
                    'background_url'        => $this->background_url,
                    'headline'              => $this->headline,
                    'subheadline'           => $this->subheadline,
                    'badge_text'            => $this->badge_text,
                    'show_badge'            => (bool) $this->show_badge,
                    'terms_text'            => $this->terms_text,
                    'support_phone'         => $this->support_phone,
                    'support_whatsapp'      => $this->support_whatsapp,
                    'theme_layout'          => $this->theme_layout,
                    'free_trial_enabled'    => (bool) $this->free_trial_enabled,
                    'free_trial_duration'   => $this->free_trial_duration,
                    'payment_instructions'  => $this->payment_instructions,
                    'walled_garden_domains' => json_encode($domainsArray),
                    'updated_at'            => now(),
                ]
            );

            $pushedNames = [];
            $failedNames = [];

            if ($pushToRouters) {
                $routers = Router::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->get();

                foreach ($routers as $router) {
                    if (empty($router->vpn_ip) && empty($router->ip)) {
                        continue;
                    }
                    try {
                        CaptivePortalTemplates::pushPortalFilesOnly($router, $domainsArray);
                        $pushedNames[] = $router->name;
                    } catch (\Throwable $re) {
                        $failedNames[] = $router->name;
                        Log::warning("[CaptivePortalCustomizer] Push to router {$router->name} failed: " . $re->getMessage());
                    }
                }
            }

            $tplLabel = ucfirst($this->template);
            if (!empty($pushedNames)) {
                $msg = "Captive portal design '{$tplLabel}' and branding saved & pushed live to " . count($pushedNames) . " router(s): " . implode(', ', $pushedNames) . ".";
                if (!empty($failedNames)) {
                    $msg .= " (Offline/standby: " . implode(', ', $failedNames) . ")";
                }
            } elseif (!empty($failedNames)) {
                $msg = "Captive portal design '{$tplLabel}' saved! Router(s) currently unreachable (" . implode(', ', $failedNames) . ") — click 'Install to Router' once online.";
            } else {
                $msg = "Captive portal design '{$tplLabel}' and branding saved successfully!";
            }

            $this->save_status = ['status' => 'success', 'message' => $msg];
            session()->flash('success', $msg);
        } catch (\Throwable $e) {
            Log::error('[CaptivePortalCustomizer] saveSettings error: ' . $e->getMessage());
            $this->save_status = [
                'status'  => 'error',
                'message' => 'Could not save portal settings: ' . $e->getMessage(),
            ];
        }
    }

    protected function getHotspotPackagesForRouter($tenantId, $routerId = null)
    {
        $query = PackageList::forTenant($tenantId)
            ->where(function($q) {
                $q->where('type', 'hotspot')->orWhereNull('type');
            })
            ->where(function($q) {
                $q->where('show_on_site', true)->orWhere('show_public', true)->orWhereNull('show_on_site');
            });

        if ($routerId) {
            $query->where(function($q) use ($routerId) {
                $q->whereHas('routers', function($rq) use ($routerId) {
                    $rq->where('routers.id', $routerId);
                })->orDoesntHave('routers');
            });
        }

        return $query->orderBy('price')->get();
    }

    public function installToRouter()
    {
        if (!$this->selected_router_id) {
            $this->install_status = ['status' => 'error', 'message' => 'Please select a destination router first.'];
            return;
        }

        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

        // Persist latest changes first before pushing to router
        $this->saveSettings(false);

        $router = Router::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($this->selected_router_id);

        if (!$router) {
            $this->install_status = ['status' => 'error', 'message' => 'Selected router not found for your account.'];
            return;
        }

        $domainsArray = array_values(array_filter(array_map('trim', explode(',', (string) $this->walled_garden_domains))));

        try {
            $result = CaptivePortalTemplates::pushAllToRouter($router, $domainsArray);
            $fileCount = count($result['files_uploaded'] ?? []);
            $tplLabel = ucfirst($this->template);

            $this->install_status = [
                'status'  => 'success',
                'message' => "Portal '{$tplLabel}' ({$fileCount} files), Wi-Fi SSID & walled-garden rules installed live onto '{$router->name}'!",
            ];
        } catch (\Throwable $e) {
            Log::warning("[CaptivePortalCustomizer] installToRouter failed for {$router->name}: " . $e->getMessage());
            $this->install_status = [
                'status'  => 'error',
                'message' => "Settings saved, but could not reach '{$router->name}' right now: " . $e->getMessage(),
            ];
        }
    }

    public function generateLoginHtml(): string
    {
        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;
        $packages = $this->getHotspotPackagesForRouter($tenantId, $this->selected_router_id);

        return CaptivePortalTemplates::render($this->template, [
            'brand_name'           => $this->brand_name,
            'brand_color'          => $this->brand_color,
            'secondary_color'      => $this->secondary_color,
            'logo_url'             => $this->logo_url,
            'background_url'       => $this->background_url,
            'headline'             => $this->headline,
            'subheadline'          => $this->subheadline,
            'badge_text'           => $this->badge_text,
            'show_badge'           => (bool) $this->show_badge,
            'terms_text'           => $this->terms_text,
            'support_phone'        => $this->support_phone,
            'support_whatsapp'     => $this->support_whatsapp,
            'payment_instructions' => $this->payment_instructions,
        ], $packages);
    }

    public function render()
    {
        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

        $routers = Router::forTenant($tenantId)->get();
        $packages = $this->getHotspotPackagesForRouter($tenantId, $this->selected_router_id);
        $templatesCatalog = CaptivePortalTemplates::catalog();

        $previewHtml = CaptivePortalTemplates::render($this->template, [
            'brand_name'           => $this->brand_name,
            'brand_color'          => $this->brand_color,
            'secondary_color'      => $this->secondary_color,
            'logo_url'             => $this->logo_url,
            'background_url'       => $this->background_url,
            'headline'             => $this->headline,
            'subheadline'          => $this->subheadline,
            'badge_text'           => $this->badge_text,
            'show_badge'           => (bool) $this->show_badge,
            'terms_text'           => $this->terms_text,
            'support_phone'        => $this->support_phone,
            'support_whatsapp'     => $this->support_whatsapp,
            'payment_instructions' => $this->payment_instructions,
        ], $packages);

        return view('livewire.admin.captive-portal-customizer', compact('routers', 'packages', 'templatesCatalog', 'previewHtml'))->layout('layouts.app');
    }
}
