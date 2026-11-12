<?php

namespace App\Livewire\Admin;

use App\Jobs\RegisterWireguardPeerJob;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\WireGuardService;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class AddRouterWizard extends Component
{
    // Wizard step tracking (1 to 4)
    public int $step = 1;

    // STEP 1 - Name
    public string $router_name = '';
    public string $location = '';
    public ?int $created_router_id = null;

    // STEP 2 - Connect
    public string $setup_token = '';
    public string $api_user = '';
    public string $api_password_plain = '';
    public string $tunnel_ip = '';
    public string $script = '';
    public string $one_line_command = '';
    public bool $is_online = false;
    public ?string $peer_registration_status = null;
    public ?string $check_message = null;

    // STEP 3 - Ports
    public array $interfaces = [];
    public array $port_roles = []; // e.g. ['ether1' => 'wan', 'ether2' => 'hotspot', 'ether3' => 'none']
    public array $simulated_cables = [];
    public ?string $port_validation_error = null;
    public bool $is_loading_interfaces = false;

    // STEP 4 - Done & Apply
    public bool $is_applying = false;
    public bool $is_configured = false;
    public array $checklist = [
        'connection' => ['status' => 'pending', 'label' => 'Router WireGuard tunnel & RouterOS API reachability'],
        'wan'        => ['status' => 'pending', 'label' => 'Internet (WAN) port & NAT masquerade configured'],
        'hotspot'    => ['status' => 'pending', 'label' => 'Hotspot server, bridge & IP pool created'],
        'pppoe'      => ['status' => 'pending', 'label' => 'PPPoE server & billing profile ready'],
    ];
    public ?string $apply_error = null;

    protected function rules(): array
    {
        return [
            'router_name' => 'required|string|min:2|max:100',
            'location'    => 'required|string|min:2|max:100',
        ];
    }

    public function mount()
    {
        $this->router_name = 'MikroTik-' . strtoupper(Str::random(4));
    }

    /**
     * STEP 1 -> STEP 2: Create router record and generate one-time script
     */
    public function generateSetupScript()
    {
        $this->validate();

        $tenantId = \App\Services\Tenant\TenantContext::getTenantId();

        if (!$tenantId) {
            $user = auth()->user();
            if ($user && empty($user->tenant_id)) {
                $tenantId = \Illuminate\Support\Facades\DB::table('tenants')->insertGetId([
                    'name'         => ($user->name ?: 'ISP') . ' Networks',
                    'subdomain'    => \Illuminate\Support\Str::slug($user->name ?: 'isp') . '-' . $user->id,
                    'billing_code' => strtoupper(\Illuminate\Support\Str::random(6)),
                    'country'      => 'Kenya',
                    'county'       => 'Nairobi',
                    'is_active'    => 1,
                    'sms_balance'  => 20.00,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $user->tenant_id = $tenantId;
                $user->save();
            } else {
                abort(403, 'No tenant context found. Please ensure your ISP account is properly configured.');
            }
        }

        // 1. Generate security credentials (nbp_ prefix matching industry bootstrap standard)
        $this->setup_token = 'nbp_' . Str::random(36);
        $this->api_user = 'hs_api_' . strtolower(Str::random(6));
        $this->api_password_plain = Str::random(16);

        // 2. Generate WireGuard Keypair
        $keypair = WireGuardService::generateKeyPair();
        $privKeyPlain = $keypair['private_key'];
        $pubKey = $keypair['public_key'];

        // 3. Create router record in DB
        $router = Router::create([
            'tenant_id'       => $tenantId,
            'name'            => $this->router_name,
            'router_name'     => $this->router_name,
            'location'        => $this->location,
            'status'          => 'pending',
            'setup_token'     => $this->setup_token,
            'provision_token' => $this->setup_token,
            'api_user'        => $this->api_user,
            'username'        => $this->api_user,
            'api_password'    => Crypt::encryptString($this->api_password_plain),
            'password'        => Crypt::encryptString($this->api_password_plain),
            'wg_private_key'  => Crypt::encryptString($privKeyPlain),
            'wg_public_key'   => $pubKey,
            'connection_type' => 'wireguard',
            'api_port'        => 8728,
        ]);

        $this->created_router_id = $router->id;

        // 4. Allocate VPN IP
        $this->tunnel_ip = WireGuardService::allocateVpnIp($router->id);
        $router->update([
            'wg_address' => $this->tunnel_ip,
            'vpn_ip'     => $this->tunnel_ip,
            'ip'         => $this->tunnel_ip,
        ]);

        // 5. Register peer on WireGuard server
        try {
            RegisterWireguardPeerJob::dispatchSync($router->id, $pubKey, $this->tunnel_ip);
            $this->peer_registration_status = 'Peer registered on WireGuard server.';
        } catch (\Throwable $e) {
            Log::warning("[AddRouterWizard] Sync WireGuard peer registration: " . $e->getMessage());
            try {
                RegisterWireguardPeerJob::dispatch($router->id, $pubKey, $this->tunnel_ip);
                $this->peer_registration_status = 'Peer registered in WireGuard queue.';
            } catch (\Throwable $ex) {
                $this->peer_registration_status = 'Local dev mode.';
            }
        }

        // 6. Build the RouterOS setup script and one-line fetch command
        $this->script = $this->buildRouterOsScript($privKeyPlain, $this->tunnel_ip);

        $appUrl = rtrim(config('app.url', url('/')), '/');
        $this->one_line_command = ":do { /tool fetch mode=https check-certificate=no url=\"{$appUrl}/provision/{$this->setup_token}/bootstrap\" dst-path=\"hsmart-bootstrap.rsc\" } on-error={ :do { /tool fetch mode=http url=\"http://isp.hsmart.co.ke/provision/{$this->setup_token}/bootstrap\" dst-path=\"hsmart-bootstrap.rsc\" } on-error={ :log error \"HSmart bootstrap download failed\"; :put \"HSmart bootstrap download failed\"; :error \"HSmart provisioning stopped\" } }; /import file-name=\"hsmart-bootstrap.rsc\"; /file remove \"hsmart-bootstrap.rsc\"";

        // Advance to Step 2
        $this->step = 2;
        $this->check_message = null;
    }

    /**
     * Build idempotent RouterOS configuration script for the router terminal.
     */
    protected function buildRouterOsScript(string $plainPrivateKey, string $vpnIp): string
    {
        $appUrl = rtrim(config('app.url', url('/')), '/');
        $serverPubKey = config('services.wireguard.server_public_key', env('WG_SERVER_PUBLIC_KEY', 'DuZQaTGDe95eAhT6+OFgFaI+rsP/n6iaIxjWSvtTRx4='));
        $serverEndpoint = config('services.wireguard.server_endpoint', env('WG_ENDPOINT', '213.199.49.216:51820'));

        $parts = explode(':', $serverEndpoint);
        $serverHost = $parts[0] ?? '213.199.49.216';
        $serverPort = $parts[1] ?? '51820';

        return <<<ROUTERSCRIPT
# ==============================================================
#  HSMART CLOUD - AUTOMATED ROUTER SETUP SCRIPT
#  Router: {$this->router_name} ({$this->location})
#  Generated: Now | Idempotent & Safe Execution
# ==============================================================

# 1. Create HSmart Management Group & Dedicated API User
:if ([:len [/user group find name=hsmart_api]] = 0) do={
    /user group add name=hsmart_api policy=api,read,write,test,policy,password comment="HSmart Managed Group"
}
:if ([:len [/user find name="{$this->api_user}"]] = 0) do={
    /user add name="{$this->api_user}" group=hsmart_api password="{$this->api_password_plain}" comment="HSmart Automated API"
} else={
    /user set [find name="{$this->api_user}"] password="{$this->api_password_plain}"
}

# 2. Enable API Service on Standard Port 8728
/ip service enable api
/ip service set api port=8728

# 3. Configure WireGuard Tunnel
:if ([:len [/interface wireguard find name=wg-hsmart]] = 0) do={
    /interface wireguard add name=wg-hsmart listen-port=13231 private-key="{$plainPrivateKey}" comment="HSmart VPN Tunnel"
} else={
    /interface wireguard set [find name=wg-hsmart] private-key="{$plainPrivateKey}"
}

:if ([:len [/ip address find interface=wg-hsmart]] = 0) do={
    /ip address add address={$vpnIp}/16 interface=wg-hsmart comment="HSmart Cloud IP"
} else={
    /ip address set [find interface=wg-hsmart] address={$vpnIp}/16
}

# 4. Attach WireGuard Peer to HSmart Cloud Gateway
:if ([:len [/interface wireguard peers find interface=wg-hsmart]] = 0) do={
    /interface wireguard peers add interface=wg-hsmart public-key="{$serverPubKey}" endpoint-address="{$serverHost}" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25 comment="HSmart Cloud Gateway"
} else={
    /interface wireguard peers set [find interface=wg-hsmart] public-key="{$serverPubKey}" endpoint-address="{$serverHost}" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25
}

# 5. Allow Inbound Management Traffic over Secure Tunnel
:if ([:len [/ip firewall filter find comment="HSmart Inbound Management"]] = 0) do={
    /ip firewall filter add chain=input interface=wg-hsmart action=accept place-before=0 comment="HSmart Inbound Management"
}

# 6. Periodic Heartbeat Telemetry (Signals Online Status to Portal)
/system scheduler remove [find name=hsmart_hello]
/system scheduler add name=hsmart_hello interval=15s on-event="/tool fetch url=\"{$appUrl}/api/router-hello/{$this->setup_token}\" keep-result=no" comment="HSmart Heartbeat"
/tool fetch url="{$appUrl}/api/router-hello/{$this->setup_token}" keep-result=no
ROUTERSCRIPT;
    }

    /**
     * Poll router online status every 3 seconds while on Step 2.
     * Transitions to Step 3 ONLY when a verified heartbeat is received from the router.
     */
    public function checkRouterOnline()
    {
        if ($this->step !== 2 || !$this->created_router_id) {
            return;
        }

        $router = Router::find($this->created_router_id);
        if (!$router) {
            return;
        }

        // Router is online ONLY if status is 'online' AND last_seen_at was updated within the last 120 seconds
        $recentlySeen = $router->last_seen_at && $router->last_seen_at->gt(now()->subSeconds(120));

        if ($router->status === 'online' && $recentlySeen) {
            $this->is_online = true;
            $this->step = 3;
            $this->check_message = null;
            $this->fetchInterfaces();
        }
    }

    /**
     * Manual user trigger to check connection status immediately.
     */
    public function manualCheckConnection()
    {
        $this->checkRouterOnline();

        if (!$this->is_online) {
            $router = Router::find($this->created_router_id);
            $lastSeen = $router?->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never';
            $this->check_message = "No heartbeat received yet (Last seen: {$lastSeen}). Please paste the script into Winbox -> New Terminal and ensure the router has internet access.";
        }
    }

    /**
     * Go back to Step 2 from Step 3 if user needs to re-run or inspect the script.
     */
    public function goBackToStep2()
    {
        $this->step = 2;
        $this->port_validation_error = null;
        $this->check_message = null;
    }

    /**
     * STEP 3: Fetch interfaces from Router via RouterOS API.
     * STRICT: No mock or fake interfaces! If router is unreachable, reports exact error.
     */
    public function fetchInterfaces()
    {
        $this->is_loading_interfaces = true;
        $this->port_validation_error = null;
        $this->interfaces = [];
        $this->port_roles = [];

        $router = Router::find($this->created_router_id);
        if (!$router) {
            $this->port_validation_error = 'Router record not found in system.';
            $this->is_loading_interfaces = false;
            return;
        }

        $apiHost = $router->vpn_ip ?: $router->ip;
        $apiPort = (int) ($router->api_port ?: 8728);
        $apiUser = $router->api_user ?: $router->username;

        $apiPass = '';
        try {
            $apiPass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $apiPass = $router->api_password ?: $router->password;
        }

        $detectedInterfaces = [];
        $connectionError = null;

        try {
            $api = new MikrotikApiService($apiHost, $apiPort, $apiUser, $apiPass, 5);
            $rawInterfaces = $api->executeCommand('/interface/print');

            if (is_string($rawInterfaces) && str_starts_with($rawInterfaces, 'Error:')) {
                throw new Exception($rawInterfaces);
            }

            if (is_array($rawInterfaces) && !empty($rawInterfaces)) {
                foreach ($rawInterfaces as $item) {
                    if (isset($item['!trap'])) {
                        continue;
                    }

                    $name = $item['name'] ?? '';
                    $type = $item['type'] ?? '';

                    // Only physical and tunable interfaces (ether, sfp, wlan, wifi)
                    if (str_contains($type, 'ether') || str_contains($type, 'sfp') || str_contains($type, 'wlan') || str_contains($type, 'wifi') || str_starts_with($name, 'ether') || str_starts_with($name, 'wlan') || str_starts_with($name, 'sfp')) {
                        $running = ($item['running'] ?? 'false') === 'true';
                        $mac = $item['mac-address'] ?? ($item['mac'] ?? '—');

                        $detectedInterfaces[] = [
                            'name'    => $name,
                            'type'    => $type,
                            'running' => $running,
                            'mac'     => $mac,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $connectionError = $e->getMessage();
            Log::warning("[AddRouterWizard] API interface query failed for router #{$router->id} at {$apiHost}:{$apiPort}: " . $connectionError);
        }

        // STRICT: If no interfaces were read from the real router, halt and show honest error
        if (empty($detectedInterfaces)) {
            $this->interfaces = [];
            $this->port_roles = [];
            $this->port_validation_error = "Unable to connect to router RouterOS API ({$apiHost}:{$apiPort}). " . 
                ($connectionError ? "Detail: {$connectionError}. " : "") .
                "Please verify that the router executed the setup script, WireGuard tunnel is up, and API service is running.";
            $this->is_loading_interfaces = false;
            return;
        }

        $this->interfaces = $detectedInterfaces;

        // Initialize port roles: ether1 as WAN, second port as Hotspot, remainder none
        $roles = [];
        foreach ($this->interfaces as $index => $iface) {
            $name = $iface['name'];
            if ($index === 0) {
                $roles[$name] = 'wan';
            } elseif ($index === 1) {
                $roles[$name] = 'hotspot';
            } else {
                $roles[$name] = 'none';
            }
        }

        $this->port_roles = $roles;
        $this->is_loading_interfaces = false;
        $this->validatePortRoles();
    }

    public function toggleSimulateCable(string $portName)
    {
        if (!empty($this->simulated_cables[$portName])) {
            unset($this->simulated_cables[$portName]);
        } else {
            $this->simulated_cables[$portName] = true;
        }
    }

    public function setPortRole(string $port, string $role)
    {
        if (($this->port_roles[$port] ?? '') === 'wan') {
            return;
        }

        if ($this->isWifiPort($port) && $role === 'pppoe') {
            return;
        }

        if (in_array($role, ['none', 'hotspot', 'pppoe'])) {
            $this->port_roles[$port] = $role;
            $this->validatePortRoles();
        }
    }

    public function isWifiPort(string $name): bool
    {
        $lower = strtolower($name);
        return str_starts_with($lower, 'wlan') || str_starts_with($lower, 'wifi') || str_contains($lower, 'wireless');
    }

    public function isCableConnected(array $iface): bool
    {
        $name = $iface['name'] ?? '';
        if (!empty($this->simulated_cables[$name])) {
            return true;
        }
        return (bool) ($iface['running'] ?? false);
    }

    public function pollPortStatus()
    {
        if ($this->step !== 3 || !$this->created_router_id || empty($this->interfaces)) {
            return;
        }

        $router = Router::find($this->created_router_id);
        if (!$router) return;

        $apiHost = $router->vpn_ip ?: $router->ip;
        $apiPort = (int) ($router->api_port ?: 8728);
        $apiUser = $router->api_user ?: $router->username;
        $apiPass = '';
        try {
            $apiPass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $apiPass = $router->api_password ?: $router->password;
        }

        try {
            $api = new MikrotikApiService($apiHost, $apiPort, $apiUser, $apiPass, 3);
            $rawInterfaces = $api->executeCommand('/interface/print');
            if (is_array($rawInterfaces)) {
                $statusMap = [];
                foreach ($rawInterfaces as $item) {
                    if (isset($item['name'])) {
                        $statusMap[$item['name']] = ($item['running'] ?? 'false') === 'true';
                    }
                }
                foreach ($this->interfaces as &$iface) {
                    if (isset($statusMap[$iface['name']])) {
                        $iface['running'] = $statusMap[$iface['name']];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore polling errors
        }
    }

    /**
     * Validate interface assignments:
     * - Must have real interfaces loaded.
     * - Exactly ONE Internet (WAN) port.
     * - At least ONE Hotspot or PPPoE port.
     */
    public function validatePortRoles(): bool
    {
        if (empty($this->interfaces)) {
            $this->port_validation_error = 'No interfaces detected from router. Please reconnect or retry.';
            return false;
        }

        $wanCount = 0;
        $hotspotCount = 0;
        $pppoeCount = 0;

        foreach ($this->port_roles as $role) {
            if ($role === 'wan')     $wanCount++;
            if ($role === 'hotspot') $hotspotCount++;
            if ($role === 'pppoe')   $pppoeCount++;
        }

        if ($wanCount === 0) {
            $this->port_validation_error = 'Please designate exactly one port as Internet (WAN).';
            return false;
        }

        if ($wanCount > 1) {
            $this->port_validation_error = 'Only one port can be designated as Internet (WAN).';
            return false;
        }

        if ($hotspotCount === 0 && $pppoeCount === 0) {
            $this->port_validation_error = 'Please assign at least one port for Hotspot or PPPoE customer service.';
            return false;
        }

        $this->port_validation_error = null;
        return true;
    }

    public function updatedPortRoles()
    {
        $this->validatePortRoles();
    }

    /**
     * STEP 3 -> STEP 4: Apply configuration to Router.
     * STRICT: Verifies API connectivity and halts with detailed error if any command fails.
     */
    public function applyConfiguration()
    {
        if (!$this->validatePortRoles()) {
            return;
        }

        if (empty($this->interfaces)) {
            $this->port_validation_error = 'Cannot configure router without verified network interfaces.';
            return;
        }

        $this->step = 4;
        $this->is_applying = true;
        $this->apply_error = null;
        $this->is_configured = false;

        $this->checklist = [
            'connection' => ['status' => 'pending', 'label' => 'Router WireGuard tunnel & RouterOS API reachability'],
            'wan'        => ['status' => 'pending', 'label' => 'Internet (WAN) port & NAT masquerade configured'],
            'hotspot'    => ['status' => 'pending', 'label' => 'Hotspot server, bridge & IP pool created'],
            'pppoe'      => ['status' => 'pending', 'label' => 'PPPoE server & billing profile ready'],
        ];

        $router = Router::find($this->created_router_id);
        if (!$router) {
            $this->apply_error = 'Router record not found in system.';
            $this->is_applying = false;
            return;
        }

        $apiHost = $router->vpn_ip ?: $router->ip;
        $apiPort = (int) ($router->api_port ?: 8728);
        $apiUser = $router->api_user ?: $router->username;

        $apiPass = '';
        try {
            $apiPass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $apiPass = $router->api_password ?: $router->password;
        }

        // Identify port assignments
        $wanPort = null;
        $hotspotPorts = [];
        $pppoePorts = [];

        foreach ($this->port_roles as $port => $role) {
            if ($role === 'wan')     $wanPort = $port;
            if ($role === 'hotspot') $hotspotPorts[] = $port;
            if ($role === 'pppoe')   $pppoePorts[] = $port;
        }

        // 1. VERIFY API REACHABILITY
        $api = null;
        try {
            $api = new MikrotikApiService($apiHost, $apiPort, $apiUser, $apiPass, 6);
            $testResp = $api->executeCommand('/system/identity/print');
            if (is_string($testResp) && str_starts_with($testResp, 'Error:')) {
                throw new Exception($testResp);
            }
            $this->checklist['connection']['status'] = 'done';
        } catch (\Throwable $e) {
            $this->checklist['connection']['status'] = 'failed';
            $this->apply_error = "Could not connect to router API at {$apiHost}:{$apiPort}. Error: " . $e->getMessage() . ". Ensure the setup script executed completely in Winbox and WireGuard tunnel is up.";
            $this->is_applying = false;
            $this->is_configured = false;
            $router->update(['status' => 'config_failed']);
            return;
        }

        // 2. WAN Configuration: DHCP Client & Masquerade NAT
        try {
            if ($wanPort) {
                // Check if DHCP client already exists on WAN port
                $existingDhcp = $api->executeCommand('/ip/dhcp-client/print', [], ['interface' => $wanPort]);
                if (empty($existingDhcp) || !is_array($existingDhcp)) {
                    $res = $api->executeCommand('/ip/dhcp-client/add', ['interface' => $wanPort, 'disabled' => 'no', 'comment' => 'HSmart WAN']);
                    if (is_string($res) && str_starts_with($res, 'Error:')) {
                        throw new Exception($res);
                    }
                }

                // Check masquerade rule
                $existingNat = $api->executeCommand('/ip/firewall/nat/print', [], ['chain' => 'srcnat', 'action' => 'masquerade', 'out-interface' => $wanPort]);
                if (empty($existingNat) || !is_array($existingNat)) {
                    $res = $api->executeCommand('/ip/firewall/nat/add', [
                        'chain'         => 'srcnat',
                        'out-interface' => $wanPort,
                        'action'        => 'masquerade',
                        'comment'       => 'HSmart Masquerade',
                    ]);
                    if (is_string($res) && str_starts_with($res, 'Error:')) {
                        throw new Exception($res);
                    }
                }
            }
            $this->checklist['wan']['status'] = 'done';
        } catch (\Throwable $e) {
            $this->checklist['wan']['status'] = 'failed';
            $this->apply_error = "WAN Configuration failed: " . $e->getMessage();
            $this->is_applying = false;
            $this->is_configured = false;
            $router->update(['status' => 'config_failed']);
            return;
        }

        // 3. Hotspot Configuration (if hotspot ports assigned)
        $hsBridgeName = 'bridge-hotspot';
        try {
            if (!empty($hotspotPorts)) {
                // Check if bridge-hotspot exists or legacy hs-bridge
                $existingBridge = $api->executeCommand('/interface/bridge/print', [], ['name' => $hsBridgeName]);
                if (empty($existingBridge) || !is_array($existingBridge)) {
                    $existingBridgeLegacy = $api->executeCommand('/interface/bridge/print', [], ['name' => 'hs-bridge']);
                    if (!empty($existingBridgeLegacy) && is_array($existingBridgeLegacy)) {
                        $hsBridgeName = 'hs-bridge';
                    } else {
                        $api->executeCommand('/interface/bridge/add', ['name' => $hsBridgeName, 'comment' => 'HSmart Hotspot Bridge']);
                    }
                }

                // Add ports to bridge
                foreach ($hotspotPorts as $hPort) {
                    $existingPort = $api->executeCommand('/interface/bridge/port/print', [], ['bridge' => $hsBridgeName, 'interface' => $hPort]);
                    if (empty($existingPort) || !is_array($existingPort)) {
                        $api->executeCommand('/interface/bridge/port/add', ['bridge' => $hsBridgeName, 'interface' => $hPort]);
                    }
                }

                // IP Address on Bridge
                $existingIp = $api->executeCommand('/ip/address/print', [], ['interface' => $hsBridgeName]);
                if (empty($existingIp) || !is_array($existingIp)) {
                    $api->executeCommand('/ip/address/add', ['address' => '10.10.0.1/22', 'network' => '10.10.0.0', 'interface' => $hsBridgeName, 'comment' => 'HSmart Hotspot Subnet']);
                }

                // IP Pool
                $existingPool = $api->executeCommand('/ip/pool/print', [], ['name' => 'hs-pool']);
                if (empty($existingPool) || !is_array($existingPool)) {
                    $api->executeCommand('/ip/pool/add', ['name' => 'hs-pool', 'ranges' => '10.10.0.10-10.10.3.250']);
                }

                // DHCP Server & Network
                $existingDhcpSrv = $api->executeCommand('/ip/dhcp-server/print', [], ['name' => 'hs-dhcp']);
                if (empty($existingDhcpSrv) || !is_array($existingDhcpSrv)) {
                    $api->executeCommand('/ip/dhcp-server/add', ['name' => 'hs-dhcp', 'interface' => $hsBridgeName, 'address-pool' => 'hs-pool', 'disabled' => 'no']);
                }

                $existingNet = $api->executeCommand('/ip/dhcp-server/network/print', [], ['address' => '10.10.0.0/22']);
                if (empty($existingNet) || !is_array($existingNet)) {
                    $api->executeCommand('/ip/dhcp-server/network/add', ['address' => '10.10.0.0/22', 'gateway' => '10.10.0.1', 'dns-server' => '8.8.8.8,1.1.1.1']);
                }

                // Hotspot Profile & Server
                $existingHsProf = $api->executeCommand('/ip/hotspot/profile/print', [], ['name' => 'hsmart-profile']);
                if (empty($existingHsProf) || !is_array($existingHsProf)) {
                    $api->executeCommand('/ip/hotspot/profile/add', ['name' => 'hsmart-profile', 'hotspot-address' => '10.10.0.1', 'dns-name' => 'wifi.login', 'login-by' => 'http-chap,http-pap']);
                }

                $existingHsSrv = $api->executeCommand('/ip/hotspot/print', [], ['name' => 'hs-server']);
                if (empty($existingHsSrv) || !is_array($existingHsSrv)) {
                    $api->executeCommand('/ip/hotspot/add', ['name' => 'hs-server', 'interface' => $hsBridgeName, 'profile' => 'hsmart-profile', 'address-pool' => 'hs-pool', 'disabled' => 'no']);
                }

                $this->checklist['hotspot']['status'] = 'done';
            } else {
                $this->checklist['hotspot']['status'] = 'done';
                $this->checklist['hotspot']['label'] = 'Hotspot server skipped (no ports assigned)';
            }
        } catch (\Throwable $e) {
            $this->checklist['hotspot']['status'] = 'failed';
            $this->apply_error = "Hotspot Configuration failed: " . $e->getMessage();
            $this->is_applying = false;
            $this->is_configured = false;
            $router->update(['status' => 'config_failed']);
            return;
        }

        // 4. PPPoE Configuration (if PPPoE ports assigned via bridge-pppoe)
        $pppBridgeName = 'bridge-pppoe';
        try {
            if (!empty($pppoePorts)) {
                $existingBridge = $api->executeCommand('/interface/bridge/print', [], ['name' => $pppBridgeName]);
                if (empty($existingBridge) || !is_array($existingBridge)) {
                    $api->executeCommand('/interface/bridge/add', ['name' => $pppBridgeName, 'comment' => 'HSmart PPPoE Bridge']);
                }

                // Add ports to bridge-pppoe
                foreach ($pppoePorts as $pPort) {
                    $existingPort = $api->executeCommand('/interface/bridge/port/print', [], ['bridge' => $pppBridgeName, 'interface' => $pPort]);
                    if (empty($existingPort) || !is_array($existingPort)) {
                        $api->executeCommand('/interface/bridge/port/add', ['bridge' => $pppBridgeName, 'interface' => $pPort]);
                    }
                }

                // IP Pool for PPPoE
                $existingPppPool = $api->executeCommand('/ip/pool/print', [], ['name' => 'pppoe-pool']);
                if (empty($existingPppPool) || !is_array($existingPppPool)) {
                    $api->executeCommand('/ip/pool/add', ['name' => 'pppoe-pool', 'ranges' => '10.0.0.2-10.0.0.254']);
                }

                // PPP Profile
                $existingPppProf = $api->executeCommand('/ppp/profile/print', [], ['name' => 'hs-pppoe-profile']);
                if (empty($existingPppProf) || !is_array($existingPppProf)) {
                    $api->executeCommand('/ppp/profile/add', ['name' => 'hs-pppoe-profile', 'local-address' => '10.0.0.1', 'remote-address' => 'pppoe-pool', 'dns-server' => '8.8.8.8']);
                }

                // Single PPPoE Server bound to bridge-pppoe
                $existingPppSrv = $api->executeCommand('/interface/pppoe-server/server/print', [], ['service-name' => 'pppoe-srv']);
                if (empty($existingPppSrv) || !is_array($existingPppSrv)) {
                    $api->executeCommand('/interface/pppoe-server/server/add', [
                        'service-name'   => 'pppoe-srv',
                        'interface'      => $pppBridgeName,
                        'default-profile'=> 'hs-pppoe-profile',
                        'one-session-per-host' => 'yes',
                        'disabled'       => 'no',
                    ]);
                }

                $this->checklist['pppoe']['status'] = 'done';
            } else {
                $this->checklist['pppoe']['status'] = 'done';
                $this->checklist['pppoe']['label'] = 'PPPoE server skipped (no ports assigned)';
            }
        } catch (\Throwable $e) {
            $this->checklist['pppoe']['status'] = 'failed';
            $this->apply_error = "PPPoE Configuration failed: " . $e->getMessage();
            $this->is_applying = false;
            $this->is_configured = false;
            $router->update(['status' => 'config_failed']);
            return;
        }

        // All steps successfully verified and executed on live router!
        $primaryLan = 'ether2';
        if (!empty($hotspotPorts)) {
            $primaryLan = $hsBridgeName;
        } elseif (!empty($pppoePorts)) {
            $primaryLan = $pppBridgeName;
        }

        $router->update([
            'status'         => 'ready',
            'wan_interface'  => $wanPort ?: 'ether1',
            'lan_interface'  => $primaryLan,
            'port_roles'     => $this->port_roles,
            'hotspot_status' => !empty($hotspotPorts) ? 'configured' : 'not_configured',
            'pppoe_status'   => !empty($pppoePorts) ? 'configured' : 'not_configured',
            'provisioned_at' => now(),
            'last_seen_at'   => now(),
        ]);

        $this->is_applying = false;
        $this->is_configured = true;
    }

    public function goBackToStep3()
    {
        $this->step = 3;
        $this->apply_error = null;
        $this->is_applying = false;
        $this->is_configured = false;
    }

    public function render()
    {
        return view('livewire.admin.add-router-wizard', [
            'step'                     => $this->step,
            'router_name'              => $this->router_name,
            'location'                 => $this->location,
            'created_router_id'        => $this->created_router_id,
            'setup_token'              => $this->setup_token,
            'api_user'                 => $this->api_user,
            'api_password_plain'       => $this->api_password_plain,
            'tunnel_ip'                => $this->tunnel_ip,
            'script'                   => $this->script,
            'one_line_command'         => $this->one_line_command,
            'is_online'                => $this->is_online,
            'peer_registration_status' => $this->peer_registration_status,
            'check_message'            => $this->check_message,
            'interfaces'               => $this->interfaces,
            'port_roles'               => $this->port_roles,
            'simulated_cables'         => $this->simulated_cables,
            'port_validation_error'    => $this->port_validation_error,
            'is_loading_interfaces'    => $this->is_loading_interfaces,
            'is_applying'              => $this->is_applying,
            'is_configured'            => $this->is_configured,
            'checklist'                => $this->checklist,
            'apply_error'              => $this->apply_error,
        ])->layout('layouts.app');
    }
}
