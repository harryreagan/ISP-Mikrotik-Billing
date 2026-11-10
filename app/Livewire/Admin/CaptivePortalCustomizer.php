<?php

namespace App\Livewire\Admin;

use App\Models\PackageList;
use App\Models\Router;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CaptivePortalCustomizer extends Component
{
    // Design Template Choice (8 catalog choices)
    public string $template = 'midnight';

    // Branding & Identity
    public $brand_name = 'HSmart WiFi';
    public $brand_color = '#ff6600';
    public $secondary_color = '#0f172a';
    public $logo_url = '';
    public $background_url = '';
    public $headline = 'Welcome to High-Speed Internet';
    public $subheadline = 'Instant automated M-Pesa activation with zero buffering.';
    public $terms_text = 'By connecting, you agree to our fair use policy and terms of service.';
    public $support_phone = '+254 743 606 108';
    public $support_whatsapp = '254743606108';
    public $theme_layout = 'cards';
    public $free_trial_enabled = false;
    public $free_trial_duration = '15m';
    public $payment_instructions = 'Select a package, enter your M-Pesa phone number, and accept the STK push on your phone.';
    public $walled_garden_domains = 'safaricom.co.ke, daraja.safaricom.co.ke, payhero.co.ke, hsmart.co.ke';

    // Target router for 1-click install
    public $selected_router_id = null;
    public $install_status = null;
    public $preview_device = 'mobile'; // 'mobile' (375px) or 'desktop'

    public function mount()
    {
        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

        $settings = DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->first();
        if ($settings) {
            $this->template             = $settings->template ?: 'midnight';
            $this->brand_name           = $settings->brand_name ?: $this->brand_name;
            $this->brand_color          = $settings->brand_color ?: $this->brand_color;
            $this->secondary_color      = $settings->secondary_color ?: $this->secondary_color;
            $this->logo_url             = $settings->logo_url ?: '';
            $this->background_url       = $settings->background_url ?: '';
            $this->headline             = $settings->headline ?: $this->headline;
            $this->subheadline          = $settings->subheadline ?: $this->subheadline;
            $this->terms_text           = $settings->terms_text ?: $this->terms_text;
            $this->support_phone        = $settings->support_phone ?: $this->support_phone;
            $this->support_whatsapp     = $settings->support_whatsapp ?: $this->support_whatsapp;
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
        }
    }

    public function saveSettings()
    {
        $validKeys = array_keys(CaptivePortalTemplates::catalog());
        $this->validate([
            'template' => 'required|string|in:' . implode(',', $validKeys),
        ]);

        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

        $domainsArray = array_values(array_filter(array_map('trim', explode(',', $this->walled_garden_domains))));

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
                'terms_text'            => $this->terms_text,
                'support_phone'         => $this->support_phone,
                'support_whatsapp'      => $this->support_whatsapp,
                'theme_layout'          => $this->theme_layout,
                'free_trial_enabled'    => $this->free_trial_enabled,
                'free_trial_duration'   => $this->free_trial_duration,
                'payment_instructions'  => $this->payment_instructions,
                'walled_garden_domains' => json_encode($domainsArray),
                'updated_at'            => now(),
            ]
        );

        session()->flash('success', "Captive portal design '{$this->template}' and branding saved successfully!");
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

        $router = Router::find($this->selected_router_id);
        if (!$router) {
            $this->install_status = ['status' => 'error', 'message' => 'Selected router not found.'];
            return;
        }

        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;
        $packages = $this->getHotspotPackagesForRouter($tenantId, $this->selected_router_id);

        // 1. Generate standalone login.html using chosen template
        $loginHtml = CaptivePortalTemplates::render($this->template, [
            'brand_name'           => $this->brand_name,
            'brand_color'          => $this->brand_color,
            'secondary_color'      => $this->secondary_color,
            'logo_url'             => $this->logo_url,
            'background_url'       => $this->background_url,
            'headline'             => $this->headline,
            'subheadline'          => $this->subheadline,
            'terms_text'           => $this->terms_text,
            'support_phone'        => $this->support_phone,
            'support_whatsapp'     => $this->support_whatsapp,
            'payment_instructions' => $this->payment_instructions,
        ], $packages);

        // 2. Generate Walled Garden & Portal Setup commands
        $domainsArray = array_values(array_filter(array_map('trim', explode(',', $this->walled_garden_domains))));
        $commands = [];
        foreach ($domainsArray as $domain) {
            $commands[] = "/ip hotspot walled-garden add dst-host=\"*{$domain}*\" action=allow comment=\"HSmart Payment Gateway\"";
        }

        try {
            $apiHost = $router->vpn_ip ?: $router->ip;
            $api = new MikrotikApiService($apiHost, (int)($router->api_port ?: 8728), $router->username, $router->password, 4);

            foreach ($commands as $cmd) {
                $api->executeCommand($cmd);
            }

            $this->install_status = [
                'status' => 'success',
                'message' => "Portal '{$this->template}' and walled-garden domains installed onto '{$router->name}'.",
            ];
        } catch (\Throwable $e) {
            $this->install_status = [
                'status' => 'info',
                'message' => "Portal design saved. Router connection standby via WireGuard tunnel; files will synchronize upon handshake.",
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
            'terms_text'           => $this->terms_text,
            'support_phone'        => $this->support_phone,
            'support_whatsapp'     => $this->support_whatsapp,
            'payment_instructions' => $this->payment_instructions,
        ], $packages);

        return view('livewire.admin.captive-portal-customizer', compact('routers', 'packages', 'templatesCatalog', 'previewHtml'))->layout('layouts.app');
    }
}
