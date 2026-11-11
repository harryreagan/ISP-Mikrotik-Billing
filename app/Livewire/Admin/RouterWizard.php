<?php

namespace App\Livewire\Admin;

use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\ProvisioningTokenService;
use App\Services\RadiusService;
use App\Services\RouterScriptGeneratorService;
use App\Services\WireGuardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class RouterWizard extends Component
{
    // Wizard step tracking (1 to 4)
    public $step = 1;

    // Step 1: Connection Details
    public $router_name = '';
    public $connection_mode = 'wireguard'; // 'direct' or 'wireguard'
    public $host = '';
    public $api_port = 8728;
    public $username = 'admin';
    public $password = '';
    public $connection_tested = false;
    public $test_status = null;
    public $test_message = null;

    // Step 2: Hardware & Detected Info
    public $identity = '';
    public $model = 'MikroTik RouterBOARD';
    public $ros_version = 'v7.16';
    public $interfaces = [];
    public $existing_config = [
        'hotspot_servers' => [],
        'pppoe_servers'   => [],
        'ip_pools'        => [],
        'profiles'        => [],
    ];

    // Step 3: Interface Role Assignments
    public $wan_interface = 'ether1';
    public $hotspot_interfaces = ['ether2'];
    public $pppoe_interfaces = ['ether3'];
    public $lan_interface = 'bridge1';

    // Step 4: Mode & Auto-configuration
    public $service_mode = 'both'; // 'hotspot', 'pppoe', 'both'
    public $config_action = 'auto'; // 'auto' or 'existing'
    public $hotspot_subnet = '10.5.50.0/24';
    public $pppoe_pool = '10.0.0.0/24';
    public $provision_script = '';
    public $provision_command = '';
    public $created_router_id = null;
    public $is_finished = false;

    public function mount()
    {
        $this->router_name = 'MikroTik-' . strtoupper(Str::random(4));
    }

    public function testConnection()
    {
        $this->connection_tested = true;

        if ($this->connection_mode === 'wireguard') {
            $this->test_status = 'success';
            $this->test_message = 'WireGuard Tunnel Mode selected: Remote connection will establish via encrypted tunnel automatically.';
            return;
        }

        $this->validate([
            'host'     => 'required',
            'api_port' => 'required|numeric|between:1,65535',
            'username' => 'required|string',
        ]);

        try {
            $api = new MikrotikApiService($this->host, (int)$this->api_port, $this->username, $this->password, 4);
            $res = $api->executeCommand('/system/resource/print');

            if (is_array($res) && !empty($res)) {
                $this->test_status = 'success';
                $this->test_message = 'Connection successful! RouterOS responded in ' . ($res[0]['uptime'] ?? 'active') . '.';
                
                // Prefill hardware details
                $this->model = $res[0]['board-name'] ?? ($res[0]['platform'] ?? 'RouterBOARD');
                $this->ros_version = $res[0]['version'] ?? '7.x';
            } else {
                $this->test_status = 'error';
                $this->test_message = 'Router reached but authentication failed or API service returned empty response.';
            }
        } catch (\Throwable $e) {
            $this->test_status = 'error';
            $msg = $e->getMessage();
            if (str_contains($msg, 'timed out') || str_contains($msg, 'refused')) {
                $this->test_message = "Port {$this->api_port} unreachable or closed on {$this->host}. Verify IP and ensure '/ip service enable api' is active.";
            } elseif (str_contains($msg, 'login') || str_contains($msg, 'credentials')) {
                $this->test_message = "Invalid API credentials for user '{$this->username}'.";
            } else {
                $this->test_message = "Connection error: " . $msg;
            }
        }
    }

    public function goToStep2()
    {
        $this->validate([
            'router_name' => 'required|string|max:255',
        ]);

        // Attempt live fetch if direct mode
        if ($this->connection_mode === 'direct' && $this->test_status === 'success') {
            $this->fetchLiveInterfacesAndConfig();
        } else {
            // Sensible standard defaults for WireGuard / NAT routers
            $this->interfaces = [
                ['name' => 'ether1', 'type' => 'ether', 'running' => true, 'mac' => 'DC:2C:6E:01:23:45', 'comment' => 'Internet Gateway'],
                ['name' => 'ether2', 'type' => 'ether', 'running' => true, 'mac' => 'DC:2C:6E:01:23:46', 'comment' => 'Hotspot AP'],
                ['name' => 'ether3', 'type' => 'ether', 'running' => false, 'mac' => 'DC:2C:6E:01:23:47', 'comment' => 'PPPoE Distribution'],
                ['name' => 'ether4', 'type' => 'ether', 'running' => false, 'mac' => 'DC:2C:6E:01:23:48', 'comment' => 'LAN Clients'],
                ['name' => 'ether5', 'type' => 'ether', 'running' => false, 'mac' => 'DC:2C:6E:01:23:49', 'comment' => ''],
                ['name' => 'bridge1', 'type' => 'bridge', 'running' => true, 'mac' => 'DC:2C:6E:01:23:45', 'comment' => 'Local Bridge'],
            ];
            $this->existing_config = [
                'hotspot_servers' => [],
                'pppoe_servers'   => [],
                'ip_pools'        => ['dhcp-pool-1 (192.168.88.10-192.168.88.254)'],
                'profiles'        => ['default'],
            ];
        }

        $this->step = 2;
    }

    public function fetchLiveInterfacesAndConfig()
    {
        try {
            $api = new MikrotikApiService($this->host, (int)$this->api_port, $this->username, $this->password, 5);

            // Fetch identity
            $idRes = $api->executeCommand('/system/identity/print');
            if (is_array($idRes) && isset($idRes[0]['name'])) {
                $this->identity = $idRes[0]['name'];
            }

            // Fetch interfaces
            $ifRes = $api->executeCommand('/interface/print');
            $parsedIfaces = [];
            if (is_array($ifRes)) {
                foreach ($ifRes as $if) {
                    $parsedIfaces[] = [
                        'name'    => $if['name'] ?? 'unknown',
                        'type'    => $if['type'] ?? 'ether',
                        'running' => ($if['running'] ?? 'false') === 'true',
                        'mac'     => $if['mac-address'] ?? '',
                        'comment' => $if['comment'] ?? '',
                    ];
                }
            }
            $this->interfaces = $parsedIfaces;

            // Detect existing configs
            $hs = $api->executeCommand('/ip/hotspot/print');
            $pppoe = $api->executeCommand('/interface/pppoe-server/server/print');
            $pools = $api->executeCommand('/ip/pool/print');
            $profiles = $api->executeCommand('/ip/hotspot/user/profile/print');

            $this->existing_config = [
                'hotspot_servers' => is_array($hs) ? array_column($hs, 'name') : [],
                'pppoe_servers'   => is_array($pppoe) ? array_column($pppoe, 'service-name') : [],
                'ip_pools'        => is_array($pools) ? array_column($pools, 'name') : [],
                'profiles'        => is_array($profiles) ? array_column($profiles, 'name') : [],
            ];
        } catch (\Throwable $e) {
            Log::warning("[RouterWizard] Live interface query failed: " . $e->getMessage());
        }
    }

    public function goToStep3()
    {
        $this->step = 3;
    }

    public function goToStep4()
    {
        $this->step = 4;
    }

    public function finalizeRouter()
    {
        $tenantId = \App\Services\Tenant\TenantContext::getTenantId();
        if (!$tenantId) {
            abort(403, 'No tenant context found. Please ensure you are logged into a valid ISP account.');
        }

        $services = [];
        if (in_array($this->service_mode, ['hotspot', 'both'])) {
            $services[] = 'hotspot';
        }
        if (in_array($this->service_mode, ['pppoe', 'both'])) {
            $services[] = 'pppoe';
        }

        // 1. Create Router in Database
        $router = new Router();
        $router->tenant_id       = $tenantId;
        $router->router_uuid     = (string) Str::uuid();
        $router->name            = $this->router_name;
        $router->router_name     = $this->router_name;
        $router->connection_type = $this->connection_mode;
        $router->username        = $this->username ?: 'admin';
        $router->password        = $this->password ?: 'HSmart!' . rand(10000, 99999);
        $router->api_port        = $this->api_port ?: 8728;
        $router->ssh_port        = 22;
        $router->lan_interface   = $this->lan_interface ?: 'ether2';
        $router->wan_interface   = $this->wan_interface ?: 'ether1';
        $router->hotspot_subnet  = $this->hotspot_subnet ?: '10.5.50.0/24';
        $router->pppoe_pool      = $this->pppoe_pool ?: '10.0.0.0/24';
        $router->services        = $services;
        $router->radius_secret   = Str::random(16);
        $router->status          = 'onboarding';
        $router->provisioning_status = 'draft';

        if ($this->connection_mode === 'wireguard') {
            $keypair = WireGuardService::generateKeyPair();
            $router->wg_private_key = $keypair['private_key'];
            $router->wg_public_key  = $keypair['public_key'];
        } else {
            $router->ip = $this->host;
            $router->ip_address = $this->host;
        }

        $router->save();

        // 2. Allocate VPN IP & register peer
        if ($this->connection_mode === 'wireguard') {
            $vpnIp = WireGuardService::allocateVpnIp($router->id);
            $router->updateQuietly(['vpn_ip' => $vpnIp, 'ip_address' => $vpnIp]);
            WireGuardService::addPeer($router->wg_public_key, $vpnIp);
        }

        // 3. Register in RADIUS
        RadiusService::registerNas($router);

        // 4. Save detected interfaces
        foreach ($this->interfaces as $iface) {
            $role = 'unassigned';
            if ($iface['name'] === $this->wan_interface) $role = 'wan';
            elseif (in_array($iface['name'], (array)$this->hotspot_interfaces)) $role = 'hotspot';
            elseif (in_array($iface['name'], (array)$this->pppoe_interfaces)) $role = 'pppoe';
            elseif ($iface['name'] === $this->lan_interface) $role = 'lan';

            DB::table('router_interfaces')->insert([
                'router_id'    => $router->id,
                'name'         => $iface['name'],
                'type'         => $iface['type'] ?? 'ether',
                'role'         => $role,
                'running'      => $iface['running'] ?? true,
                'mac_address'  => $iface['mac'] ?? null,
                'comment'      => $iface['comment'] ?? null,
                'last_seen_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // 5. Generate Provisioning Script
        $tokenData = ProvisioningTokenService::issueToken($router, 60);
        $baseUrl = config('app.url', url('/'));
        $scriptUrl = rtrim($baseUrl, '/') . "/provision/{$tokenData['plain_token']}/bootstrap";

        $this->provision_command = ":do { /file remove [find name=\"bootstrap.rsc\"] } on-error={}; /tool fetch url=\"{$scriptUrl}\" mode=https dst-path=bootstrap.rsc; :delay 2s; /import bootstrap.rsc;";
        $this->provision_script  = RouterScriptGeneratorService::generateScript($router, $tokenData['plain_token'], $baseUrl);
        $this->created_router_id = $router->id;
        $this->is_finished       = true;

        flash()->success("Router '{$router->name}' successfully provisioned into HSmart Cloud!");
    }

    public function render()
    {
        return view('livewire.admin.router-wizard')->layout('layouts.app');
    }
}

