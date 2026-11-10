<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\OfficialInfo;
use App\Models\PPPSecrets;
use App\Models\Router;
use App\Models\RouterList;
use App\Services\ProvisioningTokenService;
use App\Services\RadiusService;
use App\Services\WireGuardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class MikrotikSync extends Component
{
    use WithPagination;

    public $RouterListId;

    public $router_name;

    public $ip_address;

    public $username = 'admin';

    public $password;

    public $ssh_port = 22;

    public $api_port = 8728;

    // Advanced & Provisioning fields
    public $connection_type = 'wireguard'; // 'wireguard' | 'direct'

    public $services_hotspot = true;

    public $services_pppoe = true;

    public $lan_interface = 'ether2';

    public $wan_interface = 'ether1';

    public $hotspot_subnet = '10.5.50.0/24';

    public $pppoe_pool = '10.0.0.0/24';

    // Modal & Terminal Script display
    public $show_script_modal = false;

    public $provision_fetch_command;

    public $provision_token;

    public $provision_expires_at;

    public $active_router_id;

    public function mount()
    {
        if (! hasAccess(['Super Admin'], ['mikrotik-setup'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function render()
    {
        // Tenant-scoped router pagination
        $routers = RouterList::forTenant()->latest()->paginate(10);
        $routers->map(function ($router) {
            $router->user_list_count = PPPSecrets::where('router_name', $router->router_name ?? $router->name)->where('status', '!=', 'removed')->count();

            return $router;
        });

        return view('livewire.mikrotik-sync', ['routers' => $routers])->layout('layouts.app');
    }

    public function rules()
    {
        $uniqueRule = 'unique:routers,name,' . $this->RouterListId;

        $rules = [
            'router_name'     => ['required', 'string', 'max:255', $uniqueRule],
            'connection_type' => ['required', 'in:wireguard,direct'],
            'username'        => 'required|string|max:255',
            'lan_interface'   => 'required|string|max:64',
            'wan_interface'   => 'required|string|max:64',
        ];

        if ($this->connection_type === 'direct') {
            $rules['ip_address'] = 'required|ip';
            $rules['password']   = 'required_if:RouterListId,null|string|max:255';
            $rules['api_port']   = 'nullable|integer|min:1|max:65535';
            $rules['ssh_port']   = 'nullable|integer|min:1|max:65535';
        }

        return $rules;
    }

    public function submit()
    {
        $this->validate($this->rules());

        $user = auth()->user();
        $tenantId = $user ? ($user->tenant_id ?: 1) : 1;

        $services = [];
        if ($this->services_hotspot) {
            $services[] = 'hotspot';
        }
        if ($this->services_pppoe) {
            $services[] = 'pppoe';
        }

        if ($this->RouterListId) {
            // Update existing router
            $router = Router::find($this->RouterListId);
            if (!$router) {
                flash()->error('Router not found.');
                return;
            }

            $router->name            = $this->router_name;
            $router->connection_type = $this->connection_type;
            $router->username        = $this->username;
            $router->lan_interface   = $this->lan_interface;
            $router->wan_interface   = $this->wan_interface;
            $router->hotspot_subnet  = $this->hotspot_subnet;
            $router->pppoe_pool      = $this->pppoe_pool;
            $router->services        = $services;

            if (!empty($this->password)) {
                $router->password = $this->password;
            }

            if ($this->connection_type === 'direct') {
                $router->ip       = $this->ip_address;
                $router->api_port = $this->api_port ?: 8728;
                $router->ssh_port = $this->ssh_port ?: 22;
            }

            $router->save();
            flash()->success('Router updated successfully!');
        } else {
            // Create new router with automated WireGuard and Provisioning
            $router = new Router();
            $router->tenant_id       = $tenantId;
            $router->router_uuid     = (string) Str::uuid();
            $router->name            = $this->router_name;
            $router->connection_type = $this->connection_type;
            $router->username        = $this->username ?: 'admin';
            $router->password        = $this->password ?: 'HSmart!' . rand(10000, 99999);
            $router->api_port        = $this->api_port ?: 8728;
            $router->ssh_port        = $this->ssh_port ?: 22;
            $router->lan_interface   = $this->lan_interface ?: 'ether2';
            $router->wan_interface   = $this->wan_interface ?: 'ether1';
            $router->hotspot_subnet  = $this->hotspot_subnet ?: '10.5.50.0/24';
            $router->pppoe_pool      = $this->pppoe_pool ?: '10.0.0.0/24';
            $router->services        = $services;
            $router->radius_secret   = Str::random(16);
            $router->status          = 'onboarding';
            $router->provisioning_status = 'draft';

            if ($this->connection_type === 'wireguard') {
                $keypair = WireGuardService::generateKeyPair();
                $router->wg_private_key = $keypair['private_key'];
                $router->wg_public_key  = $keypair['public_key'];
            } else {
                $router->ip = $this->ip_address;
            }

            $router->save();

            // Allocate VPN IP & Register WireGuard Peer
            if ($this->connection_type === 'wireguard') {
                $vpnIp = WireGuardService::allocateVpnIp($router->id);
                $router->updateQuietly(['vpn_ip' => $vpnIp]);

                WireGuardService::addPeer($router->wg_public_key, $vpnIp);
            }

            // Register in FreeRADIUS NAS
            RadiusService::registerNas($router);

            // Issue Provisioning Token & Build Fetch Command
            $tokenData = ProvisioningTokenService::issueToken($router, 30);
            $baseUrl = config('app.url', url('/'));
            $scriptUrl = rtrim($baseUrl, '/') . "/provision/{$tokenData['plain_token']}/bootstrap";

            $this->provision_token         = $tokenData['plain_token'];
            $this->provision_expires_at    = $tokenData['expires_at'];
            $this->active_router_id        = $router->id;
            $this->provision_fetch_command = ":do { /file remove [find name=\"bootstrap.rsc\"] } on-error={}; /tool fetch url=\"{$scriptUrl}\" mode=https dst-path=bootstrap.rsc; :delay 2s; /import bootstrap.rsc;";

            $this->show_script_modal = true;
            flash()->success('Router created! Copy the terminal command below to bootstrap your MikroTik.');
        }

        $this->reset(['router_name', 'ip_address', 'password', 'RouterListId']);
    }

    public function generateBootstrapScript($routerId)
    {
        $router = Router::find($routerId);
        if (!$router) {
            flash()->error('Router not found.');
            return;
        }

        // Ensure WireGuard keypair & VPN IP exist
        if (empty($router->wg_private_key)) {
            $keypair = WireGuardService::generateKeyPair();
            $router->wg_private_key = $keypair['private_key'];
            $router->wg_public_key  = $keypair['public_key'];
        }

        if (empty($router->vpn_ip)) {
            $router->vpn_ip = WireGuardService::allocateVpnIp($router->id);
        }

        if (empty($router->radius_secret)) {
            $router->radius_secret = Str::random(16);
        }

        $router->save();

        if ($router->wg_public_key && $router->vpn_ip) {
            WireGuardService::addPeer($router->wg_public_key, $router->vpn_ip);
        }

        RadiusService::registerNas($router);

        $tokenData = ProvisioningTokenService::issueToken($router, 30);
        $baseUrl = config('app.url', url('/'));
        $scriptUrl = rtrim($baseUrl, '/') . "/provision/{$tokenData['plain_token']}/bootstrap";

        $this->provision_token         = $tokenData['plain_token'];
        $this->provision_expires_at    = $tokenData['expires_at'];
        $this->active_router_id        = $router->id;
        $this->provision_fetch_command = ":do { /file remove [find name=\"bootstrap.rsc\"] } on-error={}; /tool fetch url=\"{$scriptUrl}\" mode=https dst-path=bootstrap.rsc; :delay 2s; /import bootstrap.rsc;";
        $this->show_script_modal       = true;
    }

    public function closeScriptModal()
    {
        $this->show_script_modal = false;
        $this->provision_fetch_command = null;
        $this->provision_token = null;
    }

    public function checkRouterStatus($routerId)
    {
        $router = Router::find($routerId);
        if ($router && $router->status === 'online') {
            $this->closeScriptModal();
            flash()->success("Router '{$router->name}' is now ONLINE and connected!");
        }
    }

    public function connect_toggle($routerId)
    {
        $router = Router::find($routerId);
        if ($router) {
            $router->status = $router->status === 'online' ? 'offline' : 'online';
            $router->last_seen_at = now();
            $router->save();
            flash()->success('Router '.$router->name.' is now '.$router->status.'!');
        } else {
            flash()->error('Router not found!');
        }
    }

    public function delete($id)
    {
        $router = Router::find($id);
        try {
            if ($router) {
                // Remove from WireGuard peer list
                if (!empty($router->wg_public_key)) {
                    WireGuardService::removePeer($router->wg_public_key);
                }

                // Release VPN IP back to pool
                WireGuardService::releaseVpnIp($router->id);

                // Remove from FreeRADIUS NAS
                RadiusService::unregisterNas($router);

                // Delete router
                $router->delete();
                flash()->success('Router and associated WireGuard/RADIUS peers revoked and deleted successfully!');
            } else {
                flash()->error('Router not found!');
            }
        } catch (\Exception $e) {
            flash()->error('Error deleting router: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $router = Router::find($id);
        if ($router) {
            $this->RouterListId    = $router->id;
            $this->router_name     = $router->name;
            $this->connection_type = $router->connection_type ?: 'wireguard';
            $this->ip_address      = $router->ip;
            $this->username        = $router->username;
            $this->password        = '';
            $this->ssh_port        = $router->ssh_port ?: 22;
            $this->api_port        = $router->api_port ?: 8728;
            $this->lan_interface   = $router->lan_interface ?: 'ether2';
            $this->wan_interface   = $router->wan_interface ?: 'ether1';
            $this->hotspot_subnet  = $router->hotspot_subnet ?: '10.5.50.0/24';
            $this->pppoe_pool      = $router->pppoe_pool ?: '10.0.0.0/24';

            $services = is_array($router->services) ? $router->services : json_decode($router->services ?: '[]', true);
            $this->services_hotspot = in_array('hotspot', $services);
            $this->services_pppoe   = in_array('pppoe', $services);
        }
    }

    public function dataSync($routerId)
    {
        // Direct RouterOS sync fallback if reachable
        try {
            $controller = app(MikrotikController::class);
            $controller->syncWithMikrotik($routerId);
        } catch (\Throwable $e) {
            Log::warning("[MikrotikSync] dataSync warning for Router {$routerId}: " . $e->getMessage());
        }
    }

    public function allSync()
    {
        // Background sync
    }
}
