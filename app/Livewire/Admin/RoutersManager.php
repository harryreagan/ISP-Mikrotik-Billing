<?php

namespace App\Livewire\Admin;

use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\WireGuardService;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class RoutersManager extends Component
{
    use WithPagination;

    // Filters and search
    public string $search = '';
    public string $filterStatus = 'all'; // 'all', 'online', 'offline'

    // Edit Modal State
    public bool $showEditModal = false;
    public ?int $editingRouterId = null;
    public string $edit_name = '';
    public string $edit_location = '';
    public string $edit_ip = '';
    public int $edit_api_port = 8728;
    public string $edit_username = '';
    public string $edit_password = '';

    // Bootstrap Script Modal State
    public bool $showScriptModal = false;
    public ?string $modalScript = null;
    public ?string $modalRouterName = null;

    // Ports Configuration Modal State
    public bool $showPortsModal = false;
    public ?int $selectedPortsRouterId = null;
    public string $portsRouterName = '';
    public string $portsRouterModel = '';
    public string $portsRouterIp = '';
    public array $portInterfaces = [];
    public string $portRoleWan = 'ether1';
    public string $portRoleHotspot = 'ether2';
    public string $portRolePppoe = 'ether3';
    public string $portHotspotSubnet = '10.5.50.0/24';
    public string $portPppoePool = '10.0.0.0/24';

    protected $paginationTheme = 'bootstrap';

    protected function rules(): array
    {
        return [
            'edit_name'     => 'required|string|min:2|max:100',
            'edit_location' => 'nullable|string|max:100',
            'edit_ip'       => 'nullable|string|max:64',
            'edit_api_port' => 'required|integer|min:1|max:65535',
            'edit_username' => 'required|string|max:100',
            'edit_password' => 'nullable|string|max:255',
        ];
    }

    public function mount()
    {
        // Telemetry is populated in real time from database (updated by 60s heartbeats).
        // No synchronous network socket calls during mount to guarantee instant page loads (<50ms).
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    protected function getTenantId(): int
    {
        $user = auth()->user();
        return $user?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));
    }

    /**
     * Poll all online routers for the current tenant.
     */
    public function pollAllOnlineRouters(): void
    {
        $tenantId = $this->getTenantId();
        $onlineRouters = Router::where('tenant_id', $tenantId)
            ->where('status', 'online')
            ->whereNotNull('vpn_ip')
            ->get();

        foreach ($onlineRouters as $r) {
            $this->pollRouterTelemetry($r->id, true);
        }
    }

    /**
     * Poll a specific router for live hardware telemetry (CPU, RAM, Serial, Model, Uptime, Version).
     */
    public function pollRouterTelemetry(int $routerId, bool $quiet = false): bool
    {
        $router = Router::where('id', $routerId)->first();
        if (!$router) {
            return false;
        }

        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');

        $pass = '';
        try {
            $pass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $pass = $router->api_password ?: $router->password;
        }

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 3);

            // 1. Fetch System Resource (CPU, RAM, Uptime, Version, Architecture)
            $res = $api->executeCommand('/system/resource/print');
            if (!is_array($res) || empty($res)) {
                if (!$quiet) {
                    session()->flash('warning', "Router {$router->name} did not return telemetry data.");
                }
                return false;
            }

            $data = $res[0];
            $cpuLoad    = (int) ($data['cpu-load'] ?? 0);
            $freeMem    = (int) ($data['free-memory'] ?? 0);
            $totalMem   = (int) ($data['total-memory'] ?? 0);
            $memPercent = $totalMem > 0 ? (int) round((($totalMem - $freeMem) / $totalMem) * 100) : 0;
            $uptime     = (string) ($data['uptime'] ?? 'unknown');
            $version    = (string) ($data['version'] ?? $router->version);
            $board      = (string) ($data['board-name'] ?? ($router->board ?: 'MikroTik'));
            $arch       = (string) ($data['architecture-name'] ?? ($router->architecture ?: 'smips'));

            // 2. Fetch Routerboard Hardware Info (Model, Serial Number, Firmware)
            $serialNumber = $router->serial_number;
            $model        = $router->model ?: $board;
            try {
                $rbRes = $api->executeCommand('/system/routerboard/print');
                if (is_array($rbRes) && !empty($rbRes[0])) {
                    $rb = $rbRes[0];
                    if (!empty($rb['serial-number'])) {
                        $serialNumber = (string) $rb['serial-number'];
                    }
                    if (!empty($rb['board-name'])) {
                        $board = (string) $rb['board-name'];
                    }
                    if (!empty($rb['model'])) {
                        $model = (string) $rb['model'];
                    }
                }
            } catch (\Throwable $rbe) {
                Log::debug("Routerboard print warning for [{$router->name}]: " . $rbe->getMessage());
            }

            // 3. Count Active Connected Users (Hotspot & PPPoE)
            $hsCount = 0;
            try {
                $hsActive = $api->executeCommand('/ip/hotspot/active/print');
                if (is_array($hsActive)) {
                    $hsCount = count($hsActive);
                }
            } catch (\Throwable $e) {}

            $pppCount = 0;
            try {
                $pppActive = $api->executeCommand('/ppp/active/print');
                if (is_array($pppActive)) {
                    $pppCount = count($pppActive);
                }
            } catch (\Throwable $e) {}

            $activeUsers = $hsCount + $pppCount;

            $portRoles = is_array($router->port_roles) ? $router->port_roles : [];
            $portRoles['active_hs'] = $hsCount;
            $portRoles['active_ppp'] = $pppCount;
            $portRoles['active_total'] = $activeUsers;

            $updatePayload = [
                'status'         => 'online',
                'cpu_load'       => $cpuLoad,
                'memory_load'    => $memPercent,
                'total_memory'   => $totalMem,
                'free_memory'    => $freeMem,
                'uptime'         => $uptime,
                'version'        => $version,
                'board'          => $board,
                'architecture'   => $arch,
                'model'          => $model,
                'serial_number'  => $serialNumber,
                'port_roles'     => $portRoles,
                'last_seen_at'   => now(),
                'last_polled_at' => now(),
            ];

            $router->updateQuietly($updatePayload);

            if (!$quiet) {
                session()->flash('success', "Telemetry refreshed for {$router->name}: Model '{$model}', S/N '{$serialNumber}', CPU {$cpuLoad}%, RAM {$memPercent}%, Uptime {$uptime}.");
            }
            return true;

        } catch (\Throwable $e) {
            Log::warning("Could not reach router {$router->name} at {$host}:{$port} ({$e->getMessage()})");
            if (!$quiet) {
                session()->flash('error', "Could not reach router {$router->name} at {$host}:{$port} ({$e->getMessage()}).");
            }
            return false;
        }
    }

    /**
     * Issue a remote reboot command to the MikroTik router via API.
     */
    public function rebootRouter(int $routerId)
    {
        $router = Router::where('id', $routerId)->first();
        if (!$router) {
            session()->flash('error', 'Router not found.');
            return;
        }

        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');

        $pass = '';
        try {
            $pass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $pass = $router->api_password ?: $router->password;
        }

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 5);
            $api->executeCommand('/system/reboot');

            $router->updateQuietly([
                'status' => 'rebooting',
            ]);

            session()->flash('success', "Reboot signal sent to router {$router->name}. It will come back online shortly.");
        } catch (\Throwable $e) {
            session()->flash('error', "Reboot failed for router {$router->name}: " . $e->getMessage());
        }
    }

    /**
     * Open the Edit Router modal.
     */
    public function editRouter(int $routerId)
    {
        $tenantId = $this->getTenantId();
        $router = Router::where('tenant_id', $tenantId)->where('id', $routerId)->firstOrFail();

        $this->editingRouterId = $router->id;
        $this->edit_name = (string) ($router->name ?: $router->router_name);
        $this->edit_location = (string) ($router->location ?: '');
        $this->edit_ip = (string) ($router->ip ?: ($router->ip_address ?: ''));
        $this->edit_api_port = (int) ($router->api_port ?: 8728);
        $this->edit_username = (string) ($router->api_user ?: ($router->username ?: ''));
        $this->edit_password = ''; // Keep blank unless changing

        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    /**
     * Close the Edit Router modal and reset input state.
     */
    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingRouterId = null;
        $this->reset(['edit_name', 'edit_location', 'edit_ip', 'edit_username', 'edit_password', 'editingRouterId']);
        $this->resetErrorBag();
    }

    /**
     * Form submission handler matching Blade wire:submit.prevent="updateRouter".
     */
    public function updateRouter(): void
    {
        $this->saveRouter();
    }

    /**
     * Save edited router settings.
     */
    public function saveRouter()
    {
        $this->validate();

        $tenantId = $this->getTenantId();
        $router = Router::where('tenant_id', $tenantId)->where('id', $this->editingRouterId)->firstOrFail();

        $data = [
            'name'        => $this->edit_name,
            'router_name' => $this->edit_name,
            'location'    => $this->edit_location,
            'ip'          => $this->edit_ip ?: null,
            'ip_address'  => $this->edit_ip ?: null,
            'api_port'    => $this->edit_api_port,
            'api_user'    => $this->edit_username,
            'username'    => $this->edit_username,
        ];

        if (!empty($this->edit_password)) {
            $data['api_password'] = Crypt::encryptString($this->edit_password);
            $data['password']     = Crypt::encryptString($this->edit_password);
        }

        $router->updateQuietly($data);

        $this->showEditModal = false;
        $this->reset(['edit_name', 'edit_location', 'edit_ip', 'edit_username', 'edit_password', 'editingRouterId']);
        $this->resetErrorBag();

        session()->flash('success', "Router {$router->name} details updated.");
    }

    /**
     * Close the Script View modal.
     */
    public function closeScriptModal(): void
    {
        $this->showScriptModal = false;
        $this->modalScript = null;
        $this->modalRouterName = null;
    }

    /**
     * View Bootstrap Script for a router.
     */
    public function viewScript(int $routerId)
    {
        $router = Router::where('id', $routerId)->firstOrFail();

        $token = $router->setup_token ?: ('nbp_' . bin2hex(random_bytes(16)));
        if (empty($router->setup_token)) {
            $router->updateQuietly(['setup_token' => $token]);
        }

        $baseUrl = config('app.url', url('/'));
        $this->modalScript = \App\Services\RouterScriptGeneratorService::generateScript($router, $token, $baseUrl);
        $this->modalRouterName = $router->name;
        $this->showScriptModal = true;
    }

    /**
     * Delete router and release VPN IP/peers.
     */
    public function deleteRouter(int $routerId)
    {
        $router = Router::where('id', $routerId)->firstOrFail();
        $name = $router->name;

        // Release WireGuard IP
        WireGuardService::releaseVpnIp($routerId);

        // Remove peer if public key exists
        if (!empty($router->wg_public_key)) {
            WireGuardService::removePeer($router->wg_public_key);
        }

        $router->delete();

        session()->flash('success', "Router {$name} deleted successfully.");
    }

    /**
     * Open the Port Configuration Modal and query live physical interfaces.
     */
    public function openPortsModal(int $routerId): void
    {
        $router = Router::where('id', $routerId)->firstOrFail();
        $this->selectedPortsRouterId = $router->id;
        $this->portsRouterName = $router->name ?: $router->router_name;
        $this->portsRouterModel = $router->board ?: ($router->model ?: 'MikroTik RouterOS');
        $this->portsRouterIp = $router->vpn_ip ?: ($router->ip ?: '10.10.0.x');

        $portRoles = is_array($router->port_roles) ? $router->port_roles : [];
        $this->portRoleWan = $router->wan_interface ?: ($portRoles['wan'] ?? 'ether1');
        $this->portRoleHotspot = $router->lan_interface ?: ($portRoles['hotspot'] ?? 'ether2');
        $this->portRolePppoe = $portRoles['pppoe'] ?? 'ether3';
        $this->portHotspotSubnet = $router->hotspot_subnet ?: '10.5.50.0/24';
        $this->portPppoePool = $router->pppoe_pool ?: '10.0.0.0/24';
        $this->portInterfaces = [];

        // Fetch live interfaces from router
        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = '';
        try {
            $pass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $pass = $router->api_password ?: $router->password;
        }

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 3);
            $ifaces = $api->executeCommand('/interface/print');
            if (is_array($ifaces)) {
                $this->portInterfaces = collect($ifaces)
                    ->filter(function ($i) {
                        return in_array($i['type'] ?? '', ['ether', 'wlan', 'bridge']) || str_starts_with($i['name'] ?? '', 'ether');
                    })
                    ->map(function ($i) {
                        return [
                            'name'     => $i['name'] ?? '',
                            'type'     => $i['type'] ?? 'ether',
                            'running'  => ($i['running'] ?? 'false') === 'true',
                            'disabled' => ($i['disabled'] ?? 'false') === 'true',
                            'mac'      => $i['mac-address'] ?? '',
                            'comment'  => $i['comment'] ?? '',
                        ];
                    })
                    ->values()
                    ->toArray();

                // Auto-detect currently bound Hotspot server interface
                try {
                    $hs = $api->executeCommand('/ip/hotspot/print');
                    if (is_array($hs) && !empty($hs[0]['interface'])) {
                        $this->portRoleHotspot = $hs[0]['interface'];
                    }
                } catch (\Throwable $e) {}

                // Auto-detect currently bound PPPoE server interface
                try {
                    $ppp = $api->executeCommand('/interface/pppoe-server/server/print');
                    if (is_array($ppp) && !empty($ppp[0]['interface'])) {
                        $this->portRolePppoe = $ppp[0]['interface'];
                    }
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            Log::warning("[RoutersManager] Could not query live interfaces for {$router->name}: " . $e->getMessage());
        }

        // Fallback default ports if offline
        if (empty($this->portInterfaces)) {
            $this->portInterfaces = [
                ['name' => 'ether1', 'type' => 'ether', 'running' => true,  'disabled' => false, 'mac' => '', 'comment' => 'WAN Uplink'],
                ['name' => 'ether2', 'type' => 'ether', 'running' => false, 'disabled' => false, 'mac' => '', 'comment' => 'Hotspot Default'],
                ['name' => 'ether3', 'type' => 'ether', 'running' => false, 'disabled' => false, 'mac' => '', 'comment' => 'PPPoE Default'],
                ['name' => 'ether4', 'type' => 'ether', 'running' => false, 'disabled' => false, 'mac' => '', 'comment' => 'LAN Bridge'],
                ['name' => 'wlan1',  'type' => 'wlan',  'running' => false, 'disabled' => true,  'mac' => '', 'comment' => 'Wireless'],
            ];
        }

        $this->showPortsModal = true;
    }

    public function closePortsModal(): void
    {
        $this->showPortsModal = false;
        $this->selectedPortsRouterId = null;
        $this->portInterfaces = [];
    }

    /**
     * Apply and reconfigure physical port roles live on the MikroTik router.
     */
    public function applyPortConfiguration(): void
    {
        if (!$this->selectedPortsRouterId) {
            return;
        }

        $router = Router::where('id', $this->selectedPortsRouterId)->firstOrFail();

        $this->validate([
            'portRoleWan'       => 'required|string|max:32',
            'portRoleHotspot'   => 'required|string|max:32',
            'portRolePppoe'     => 'required|string|max:32',
            'portHotspotSubnet' => 'required|string|max:32',
            'portPppoePool'     => 'required|string|max:32',
        ]);

        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = '';
        try {
            $pass = Crypt::decryptString($router->api_password ?: $router->password);
        } catch (\Throwable $e) {
            $pass = $router->api_password ?: $router->password;
        }

        $appliedOnRouter = false;
        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 6);

            // 1. Hotspot IP Address
            $hsIp = '10.5.50.1/24';
            if (str_contains($this->portHotspotSubnet, '/')) {
                $base = explode('/', $this->portHotspotSubnet)[0];
                $parts = explode('.', $base);
                if (count($parts) === 4) {
                    $parts[3] = '1';
                    $hsIp = implode('.', $parts) . '/24';
                }
            }

            try {
                $addrs = $api->executeCommand('/ip/address/print');
                $foundHsIp = false;
                if (is_array($addrs)) {
                    foreach ($addrs as $addr) {
                        if (str_contains($addr['address'] ?? '', '10.5.50.') || ($addr['comment'] ?? '') === 'hsmart-hotspot') {
                            $api->executeCommand('/ip/address/set', ['interface' => $this->portRoleHotspot, 'address' => $hsIp], ['.id' => $addr['.id']]);
                            $foundHsIp = true;
                            break;
                        }
                    }
                }
                if (!$foundHsIp) {
                    $api->executeCommand('/ip/address/add', ['address' => $hsIp, 'interface' => $this->portRoleHotspot, 'comment' => 'hsmart-hotspot']);
                }
            } catch (\Throwable $e) {}

            // Hotspot IP Pool
            try {
                $pools = $api->executeCommand('/ip/pool/print');
                $foundHsPool = false;
                if (is_array($pools)) {
                    foreach ($pools as $pl) {
                        if (($pl['name'] ?? '') === 'hs-pool-10') {
                            $api->executeCommand('/ip/pool/set', ['ranges' => '10.5.50.10-10.5.50.254'], ['.id' => $pl['.id']]);
                            $foundHsPool = true;
                            break;
                        }
                    }
                }
                if (!$foundHsPool) {
                    $api->executeCommand('/ip/pool/add', ['name' => 'hs-pool-10', 'ranges' => '10.5.50.10-10.5.50.254']);
                }
            } catch (\Throwable $e) {}

            // Hotspot DHCP Server & Network
            try {
                $dhcpNet = '10.5.50.0/24';
                $dhcpGw = '10.5.50.1';
                $api->executeCommand('/ip/dns/set', ['allow-remote-requests' => 'yes', 'servers' => '8.8.8.8,1.1.1.1']);
                
                $dnets = $api->executeCommand('/ip/dhcp-server/network/print');
                $foundDnet = false;
                if (is_array($dnets)) {
                    foreach ($dnets as $dn) {
                        if (($dn['address'] ?? '') === $dhcpNet) {
                            $api->executeCommand('/ip/dhcp-server/network/set', ['gateway' => $dhcpGw, 'dns-server' => "{$dhcpGw},8.8.8.8"], ['.id' => $dn['.id']]);
                            $foundDnet = true;
                            break;
                        }
                    }
                }
                if (!$foundDnet) {
                    $api->executeCommand('/ip/dhcp-server/network/add', ['address' => $dhcpNet, 'gateway' => $dhcpGw, 'dns-server' => "{$dhcpGw},8.8.8.8", 'comment' => 'hsmart-hotspot']);
                }

                $dhcps = $api->executeCommand('/ip/dhcp-server/print');
                $foundHsDhcp = false;
                if (is_array($dhcps)) {
                    foreach ($dhcps as $d) {
                        if (($d['name'] ?? '') === 'hs-dhcp-10' || ($d['interface'] ?? '') === $this->portRoleHotspot) {
                            $api->executeCommand('/ip/dhcp-server/set', ['interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'disabled' => 'no', 'lease-time' => '1h'], ['.id' => $d['.id']]);
                            $foundHsDhcp = true;
                            break;
                        }
                    }
                }
                if (!$foundHsDhcp) {
                    $api->executeCommand('/ip/dhcp-server/add', ['name' => 'hs-dhcp-10', 'interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'lease-time' => '1h', 'disabled' => 'no']);
                }

                // NAT Masquerade
                $nats = $api->executeCommand('/ip/firewall/nat/print');
                $foundNat = false;
                if (is_array($nats)) {
                    foreach ($nats as $n) {
                        if (($n['comment'] ?? '') === 'hsmart-hotspot-masq' || (($n['src-address'] ?? '') === $dhcpNet && ($n['action'] ?? '') === 'masquerade')) {
                            $foundNat = true;
                            break;
                        }
                    }
                }
                if (!$foundNat) {
                    $api->executeCommand('/ip/firewall/nat/add', ['chain' => 'srcnat', 'action' => 'masquerade', 'src-address' => $dhcpNet, 'comment' => 'hsmart-hotspot-masq']);
                }
            } catch (\Throwable $e) {}

            // Hotspot Server Profile & Server binding
            try {
                $hsprofName = 'hsmart-hsprof';
                $hsprofs = $api->executeCommand('/ip/hotspot/profile/print');
                $foundHsprof = false;
                if (is_array($hsprofs)) {
                    foreach ($hsprofs as $hp) {
                        if (($hp['name'] ?? '') === $hsprofName) {
                            $api->executeCommand('/ip/hotspot/profile/set', [
                                'hotspot-address' => '10.5.50.1',
                                'dns-name'        => 'wifi.hsmart.co.ke',
                                'html-directory'  => 'hotspot',
                                'login-by'        => 'http-chap,http-pap,cookie',
                            ], ['.id' => $hp['.id']]);
                            $foundHsprof = true;
                            break;
                        }
                    }
                }
                if (!$foundHsprof) {
                    $api->executeCommand('/ip/hotspot/profile/add', [
                        'name'            => $hsprofName,
                        'hotspot-address' => '10.5.50.1',
                        'dns-name'        => 'wifi.hsmart.co.ke',
                        'html-directory'  => 'hotspot',
                        'login-by'        => 'http-chap,http-pap,cookie',
                    ]);
                }

                $hsServers = $api->executeCommand('/ip/hotspot/print');
                if (is_array($hsServers) && !empty($hsServers[0]['.id'])) {
                    $api->executeCommand('/ip/hotspot/set', ['interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'profile' => $hsprofName, 'disabled' => 'no'], ['.id' => $hsServers[0]['.id']]);
                } else {
                    $api->executeCommand('/ip/hotspot/add', ['name' => 'hsmart-hotspot', 'interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'profile' => $hsprofName, 'disabled' => 'no']);
                }
            } catch (\Throwable $e) {}

            // 2. PPPoE Server binding
            try {
                $pppServers = $api->executeCommand('/interface/pppoe-server/server/print');
                if (is_array($pppServers) && !empty($pppServers[0]['.id'])) {
                    $api->executeCommand('/interface/pppoe-server/server/set', ['interface' => $this->portRolePppoe, 'disabled' => 'no'], ['.id' => $pppServers[0]['.id']]);
                } else {
                    $api->executeCommand('/interface/pppoe-server/server/add', ['service-name' => 'hsmart-pppoe', 'interface' => $this->portRolePppoe, 'disabled' => 'no', 'one-session-per-host' => 'yes']);
                }
            } catch (\Throwable $e) {}

            // 3. WAN DHCP Client binding
            try {
                $dhcpClients = $api->executeCommand('/ip/dhcp-client/print');
                $foundWan = false;
                if (is_array($dhcpClients)) {
                    foreach ($dhcpClients as $dc) {
                        if (($dc['interface'] ?? '') === $this->portRoleWan) {
                            $api->executeCommand('/ip/dhcp-client/set', ['disabled' => 'no', 'add-default-route' => 'yes'], ['.id' => $dc['.id']]);
                            $foundWan = true;
                            break;
                        }
                    }
                }
                if (!$foundWan) {
                    $api->executeCommand('/ip/dhcp-client/add', ['interface' => $this->portRoleWan, 'disabled' => 'no', 'add-default-route' => 'yes']);
                }
            } catch (\Throwable $e) {}

            $appliedOnRouter = true;
        } catch (\Throwable $e) {
            Log::warning("[RoutersManager] Reconfiguring ports API error for {$router->name}: " . $e->getMessage());
        }

        // Save to Database
        $updatedRoles = array_merge(is_array($router->port_roles) ? $router->port_roles : [], [
            'wan'     => $this->portRoleWan,
            'hotspot' => $this->portRoleHotspot,
            'pppoe'   => $this->portRolePppoe,
        ]);

        $router->updateQuietly([
            'wan_interface'  => $this->portRoleWan,
            'lan_interface'  => $this->portRoleHotspot,
            'hotspot_subnet' => $this->portHotspotSubnet,
            'pppoe_pool'     => $this->portPppoePool,
            'port_roles'     => $updatedRoles,
        ]);

        $this->showPortsModal = false;

        if ($appliedOnRouter) {
            session()->flash('success', "Ports successfully reconfigured on {$router->name}! WAN: '{$this->portRoleWan}', Hotspot: '{$this->portRoleHotspot}', PPPoE: '{$this->portRolePppoe}'.");
        } else {
            session()->flash('warning', "Port roles saved in system. Router will sync upon next online contact.");
        }
    }

    public function render()
    {
        $tenantId = $this->getTenantId();

        // Base query
        $query = Router::query()->where('tenant_id', $tenantId);

        if (!empty($this->search)) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('router_name', 'like', $term)
                  ->orWhere('location', 'like', $term)
                  ->orWhere('ip', 'like', $term)
                  ->orWhere('vpn_ip', 'like', $term)
                  ->orWhere('model', 'like', $term)
                  ->orWhere('serial_number', 'like', $term);
            });
        }

        if ($this->filterStatus === 'online') {
            $query->where('status', 'online');
        } elseif ($this->filterStatus === 'offline') {
            $query->where('status', '!=', 'online');
        }

        $routers = $query->latest()->paginate(12);

        // Attach active subscribers counts
        $routers->getCollection()->transform(function ($r) {
            $rName = $r->name ?: $r->router_name;
            $pppCount = PPPSecrets::where('router_name', $rName)
                ->where('status', '!=', 'removed')
                ->count();
            $custCount = CustomersInfo::where('router_id', $r->id)->count();
            $r->subscribers_count = max($pppCount, $custCount);
            
            // Connected live count
            $roles = is_array($r->port_roles) ? $r->port_roles : [];
            $r->live_active = (int) ($roles['active_total'] ?? ($r->subscribers ?? 0));
            $r->live_hs = (int) ($roles['active_hs'] ?? 0);
            $r->live_ppp = (int) ($roles['active_ppp'] ?? 0);
            
            return $r;
        });

        // Calculate Analytics Summary
        $allTenantRouters = Router::where('tenant_id', $tenantId)->get();
        $totalRouters = $allTenantRouters->count();
        $onlineRouters = $allTenantRouters->filter(function ($r) {
            return $r->status === 'online' && $r->last_seen_at && $r->last_seen_at->gt(now()->subMinutes(5));
        })->count();

        $avgCpu = $onlineRouters > 0 
            ? (int) round($allTenantRouters->where('status', 'online')->avg('cpu_load') ?? 0) 
            : 0;

        $totalClients = PPPSecrets::whereIn('router_name', $allTenantRouters->pluck('name')->filter())
            ->where('status', '!=', 'removed')
            ->count();

        return view('livewire.admin.routers-manager', [
            'routers'          => $routers,
            'totalRouters'     => $totalRouters,
            'onlineRouters'    => $onlineRouters,
            'avgCpu'           => $avgCpu,
            'totalClients'     => $totalClients,
            'filterStatus'     => $this->filterStatus,
            'search'           => $this->search,
            'showEditModal'    => $this->showEditModal,
            'showPortsModal'   => $this->showPortsModal,
            'showScriptModal'  => $this->showScriptModal,
            'modalScript'      => $this->modalScript,
            'modalRouterName'  => $this->modalRouterName,
            'edit_name'        => $this->edit_name,
            'edit_location'    => $this->edit_location,
            'edit_ip'          => $this->edit_ip,
            'edit_api_port'    => $this->edit_api_port,
            'edit_username'    => $this->edit_username,
            'edit_password'    => $this->edit_password,
            'editingRouterId'  => $this->editingRouterId,
        ])->layout('layouts.app');
    }
}
