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

    // Hotspot Health Check Modal State
    public bool $showHealthModal = false;
    public ?int $healthRouterId = null;
    public string $healthRouterName = '';
    public array $healthChecks = [];
    public ?array $tendaWarning = null;
    public int $healthPassedCount = 0;
    public int $healthFailedCount = 0;
    public bool $isRunningHealthCheck = false;

    // Automatic Fix State
    public bool $showFixConfirmationModal = false;
    public array $pendingFixes = [];
    public bool $isFixing = false;

    // Captive Portal Modal State
    public bool $showPortalModal = false;
    public ?int $portalRouterId = null;
    public string $portalRouterName = '';
    public string $portalPreviewUrl = '';
    public string $portalQrUrl = '';
    public bool $isPushingPortal = false;

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
     * Interactive port role assignment from faceplate rack cards.
     */
    public function setPortRole(string $portName, string $role): void
    {
        if ($role === 'wan') {
            $this->portRoleWan = $portName;
            if ($this->portRoleHotspot === $portName) $this->portRoleHotspot = '';
            if ($this->portRolePppoe === $portName) $this->portRolePppoe = '';
        } elseif ($role === 'hotspot') {
            $this->portRoleHotspot = $portName;
            if ($this->portRoleWan === $portName) $this->portRoleWan = '';
            if ($this->portRolePppoe === $portName) $this->portRolePppoe = '';
        } elseif ($role === 'pppoe') {
            $this->portRolePppoe = $portName;
            if ($this->portRoleWan === $portName) $this->portRoleWan = '';
            if ($this->portRoleHotspot === $portName) $this->portRoleHotspot = '';
        } else {
            if ($this->portRoleWan === $portName) $this->portRoleWan = '';
            if ($this->portRoleHotspot === $portName) $this->portRoleHotspot = '';
            if ($this->portRolePppoe === $portName) $this->portRolePppoe = '';
        }
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

        $prevHotspot = $router->lan_interface ?: ($router->port_roles['hotspot'] ?? null);
        $prevPppoe = $router->port_roles['pppoe'] ?? null;

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
                        $addrIf = $addr['interface'] ?? '';
                        if (str_contains($addr['address'] ?? '', '10.5.50.') || ($addr['comment'] ?? '') === 'hsmart-hotspot' || ($prevHotspot && $addrIf === $prevHotspot)) {
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
                        $dIf = $d['interface'] ?? '';
                        if (($d['name'] ?? '') === 'hs-dhcp-10' || $dIf === $this->portRoleHotspot || ($prevHotspot && $dIf === $prevHotspot) || ($d['address-pool'] ?? '') === 'hs-pool-10') {
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
                $foundHsServer = false;
                if (is_array($hsServers) && !empty($hsServers)) {
                    foreach ($hsServers as $hs) {
                        $hsIf = $hs['interface'] ?? '';
                        if ($hsIf === $this->portRoleHotspot || ($prevHotspot && $hsIf === $prevHotspot) || ($hs['profile'] ?? '') === $hsprofName || ($hs['address-pool'] ?? '') === 'hs-pool-10') {
                            $api->executeCommand('/ip/hotspot/set', ['interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'profile' => $hsprofName, 'disabled' => 'no'], ['.id' => $hs['.id']]);
                            $foundHsServer = true;
                            break;
                        }
                    }
                }
                if (!$foundHsServer) {
                    $api->executeCommand('/ip/hotspot/add', ['name' => 'hsmart-hotspot', 'interface' => $this->portRoleHotspot, 'address-pool' => 'hs-pool-10', 'profile' => $hsprofName, 'disabled' => 'no']);
                }
            } catch (\Throwable $e) {}

            // 2. PPPoE Server binding
            try {
                $pppServers = $api->executeCommand('/interface/pppoe-server/server/print');
                $foundPpp = false;
                if (is_array($pppServers) && !empty($pppServers)) {
                    foreach ($pppServers as $ps) {
                        $psIf = $ps['interface'] ?? '';
                        if ($psIf === $this->portRolePppoe || ($prevPppoe && $psIf === $prevPppoe) || ($ps['service-name'] ?? '') === 'hsmart-pppoe') {
                            $api->executeCommand('/interface/pppoe-server/server/set', ['interface' => $this->portRolePppoe, 'disabled' => 'no'], ['.id' => $ps['.id']]);
                            $foundPpp = true;
                            break;
                        }
                    }
                }
                if (!$foundPpp) {
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

        $msg = "Ports successfully reconfigured on {$router->name}! WAN: '{$this->portRoleWan}', Hotspot: '{$this->portRoleHotspot}', PPPoE: '{$this->portRolePppoe}'.";
        session()->flash('success', $msg);
        $this->dispatch('show-toast', ['type' => 'success', 'message' => $msg]);
    }

    public function openHotspotCheck(int $routerId): void
    {
        $this->healthRouterId = $routerId;
        $router = Router::where('id', $routerId)->first();
        $this->healthRouterName = $router ? ($router->name ?: $router->router_name) : 'Router';
        $this->showHealthModal = true;
        $this->runHotspotCheck();
    }

    public function closeHealthModal(): void
    {
        $this->showHealthModal = false;
        $this->healthRouterId = null;
        $this->healthChecks = [];
        $this->tendaWarning = null;
    }

    public function runHotspotCheck(): void
    {
        if (!$this->healthRouterId) return;

        $this->isRunningHealthCheck = true;
        $this->healthChecks = [];
        $this->tendaWarning = null;

        $router = Router::where('id', $this->healthRouterId)->first();
        if (!$router) {
            $this->isRunningHealthCheck = false;
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

        $roles = is_array($router->port_roles) ? $router->port_roles : [];
        $hsPort = $router->lan_interface ?: ($roles['hotspot'] ?? 'ether2');

        $api = null;
        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 4);
        } catch (\Throwable $e) {
            $this->healthChecks['api'] = [
                'id' => 'api',
                'title' => 'Router API connection',
                'passed' => false,
                'detail' => 'Could not connect to router API at ' . $host . ':' . $port,
                'fix' => 'Verify WireGuard VPN tunnel is connected and API service is running on port ' . $port,
            ];
            $this->healthPassedCount = 0;
            $this->healthFailedCount = 1;
            $this->isRunningHealthCheck = false;
            return;
        }

        // a) Port assigned to hotspot (ether2) is enabled and assigned
        $checkA = ['id' => 'port', 'title' => "Hotspot port ({$hsPort}) status", 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $ifaces = $api->executeCommand('/interface/print');
            $foundIface = null;
            if (is_array($ifaces)) {
                foreach ($ifaces as $if) {
                    if (($if['name'] ?? '') === $hsPort) {
                        $foundIface = $if;
                        break;
                    }
                }
            }
            if ($foundIface) {
                $isDisabled = ($foundIface['disabled'] ?? 'false') === 'true';
                $isRunning = ($foundIface['running'] ?? 'false') === 'true';
                if ($isDisabled) {
                    $checkA['passed'] = false;
                    $checkA['detail'] = "Port {$hsPort} exists but is administratively disabled.";
                    $checkA['fix'] = "Enable port {$hsPort} in /interface set disabled=no.";
                } else {
                    $checkA['passed'] = true;
                    $checkA['detail'] = "Port {$hsPort} is enabled and assigned to Hotspot" . ($isRunning ? " (cable link active)." : " (cable unplugged - will activate when cable plugged in).");
                }
            } else {
                $checkA['detail'] = "Port {$hsPort} not found on the router.";
                $checkA['fix'] = "Reassign a valid physical port (e.g. ether2) in the Ports configuration.";
            }
        } catch (\Throwable $e) {
            $checkA['detail'] = 'Error checking interface: ' . $e->getMessage();
            $checkA['fix'] = 'Check interface configuration in RouterOS.';
        }
        $this->healthChecks['a'] = $checkA;

        // b) /ip hotspot exists, enabled and bound to interface
        $checkB = ['id' => 'hotspot_server', 'title' => 'Hotspot server instance', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $hsServers = $api->executeCommand('/ip/hotspot/print');
            $activeHs = null;
            if (is_array($hsServers)) {
                foreach ($hsServers as $hs) {
                    $if = $hs['interface'] ?? '';
                    $dis = ($hs['disabled'] ?? 'false') === 'true';
                    if (($if === $hsPort || ($hs['name'] ?? '') === 'hsmart-hotspot') && !$dis) {
                        $activeHs = $hs;
                        break;
                    }
                }
            }
            if ($activeHs) {
                $checkB['passed'] = true;
                $checkB['detail'] = "Hotspot server '{$activeHs['name']}' is running and bound to {$activeHs['interface']}.";
            } else {
                $checkB['detail'] = "No enabled Hotspot server is bound to {$hsPort}. Clients receive IP leases but cannot see the captive portal.";
                $checkB['fix'] = "Create Hotspot server on interface {$hsPort} with profile 'hsmart-hsprof'.";
            }
        } catch (\Throwable $e) {
            $checkB['detail'] = 'Error checking hotspot: ' . $e->getMessage();
            $checkB['fix'] = 'Create hotspot server in /ip hotspot.';
        }
        $this->healthChecks['b'] = $checkB;

        // c) DHCP server and IP pool on hotspot network (10.5.50.0/24)
        $checkC = ['id' => 'dhcp', 'title' => 'DHCP server & IP pool', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $dhcps = $api->executeCommand('/ip/dhcp-server/print');
            $pools = $api->executeCommand('/ip/pool/print');
            $hsDhcp = null;
            if (is_array($dhcps)) {
                foreach ($dhcps as $d) {
                    if ((($d['interface'] ?? '') === $hsPort || ($d['name'] ?? '') === 'hs-dhcp-10') && ($d['disabled'] ?? 'false') !== 'true') {
                        $hsDhcp = $d;
                        break;
                    }
                }
            }
            $hasPool = false;
            if (is_array($pools)) {
                foreach ($pools as $p) {
                    if (str_contains($p['ranges'] ?? '', '10.5.50.') || ($p['name'] ?? '') === 'hs-pool-10') {
                        $hasPool = true;
                        break;
                    }
                }
            }
            if ($hsDhcp && $hasPool) {
                $checkC['passed'] = true;
                $checkC['detail'] = "DHCP server '{$hsDhcp['name']}' is issuing leases from pool '{$hsDhcp['address-pool']}'.";
            } else {
                $checkC['detail'] = "DHCP server or IP pool for 10.5.50.0/24 is incomplete.";
                $checkC['fix'] = "Configure pool 'hs-pool-10' (10.5.50.10-10.5.50.254) and DHCP server on {$hsPort}.";
            }
        } catch (\Throwable $e) {
            $checkC['detail'] = 'Error checking DHCP: ' . $e->getMessage();
            $checkC['fix'] = 'Configure DHCP in /ip dhcp-server.';
        }
        $this->healthChecks['c'] = $checkC;

        // d) NAT masquerade rule exists for hotspot subnet
        $checkD = ['id' => 'nat', 'title' => 'Firewall NAT masquerade', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $nats = $api->executeCommand('/ip/firewall/nat/print');
            $hasMasq = false;
            if (is_array($nats)) {
                foreach ($nats as $n) {
                    if (($n['action'] ?? '') === 'masquerade' && ($n['disabled'] ?? 'false') !== 'true') {
                        if (($n['chain'] ?? '') === 'srcnat') {
                            $src = $n['src-address'] ?? '';
                            if ($src === '' || str_contains($src, '10.5.50.') || ($n['comment'] ?? '') === 'hsmart-hotspot-masq') {
                                $hasMasq = true;
                                break;
                            }
                        }
                    }
                }
            }
            if ($hasMasq) {
                $checkD['passed'] = true;
                $checkD['detail'] = "NAT masquerade rule is active for hotspot subnet (10.5.50.0/24).";
            } else {
                $checkD['detail'] = "No NAT masquerade rule found for 10.5.50.0/24. Hotspot customers cannot route to the internet.";
                $checkD['fix'] = "Add rule: /ip firewall nat add chain=srcnat action=masquerade src-address=10.5.50.0/24.";
            }
        } catch (\Throwable $e) {
            $checkD['detail'] = 'Error checking NAT: ' . $e->getMessage();
            $checkD['fix'] = 'Add NAT masquerade in /ip firewall nat.';
        }
        $this->healthChecks['d'] = $checkD;

        // e) Hotspot profile has login-by http-pap, http-chap, hotspot-address and dns-name
        $checkE = ['id' => 'profile', 'title' => 'Hotspot profile & login methods', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $profiles = $api->executeCommand('/ip/hotspot/profile/print');
            $validProf = null;
            if (is_array($profiles)) {
                foreach ($profiles as $prof) {
                    $lb = $prof['login-by'] ?? '';
                    if (str_contains($lb, 'http-pap') || str_contains($lb, 'http-chap')) {
                        $validProf = $prof;
                        break;
                    }
                }
            }
            if ($validProf) {
                $dnsName = $validProf['dns-name'] ?? 'none';
                $hsAddr = $validProf['hotspot-address'] ?? 'none';
                $checkE['passed'] = true;
                $checkE['detail'] = "Profile '{$validProf['name']}' allows HTTP PAP/CHAP. Address: {$hsAddr}, DNS: {$dnsName}.";
            } else {
                $checkE['detail'] = "Hotspot profile is missing HTTP-PAP or HTTP-CHAP login methods.";
                $checkE['fix'] = "Set profile login-by=http-chap,http-pap hotspot-address=10.5.50.1 dns-name=wifi.hsmart.co.ke.";
            }
        } catch (\Throwable $e) {
            $checkE['detail'] = 'Error checking profile: ' . $e->getMessage();
            $checkE['fix'] = 'Configure hotspot profile in /ip hotspot profile.';
        }
        $this->healthChecks['e'] = $checkE;

        // f) Walled garden allows app domain, payment gateway domains, and server IP
        $checkF = ['id' => 'walled_garden', 'title' => 'Walled garden payment bypass', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $wgEntries = $api->executeCommand('/ip/hotspot/walled-garden/print');
            $wgIpEntries = $api->executeCommand('/ip/hotspot/walled-garden/ip/print');
            $allowedHosts = [];
            if (is_array($wgEntries)) {
                foreach ($wgEntries as $wg) {
                    if (!empty($wg['dst-host'])) $allowedHosts[] = strtolower($wg['dst-host']);
                }
            }
            if (is_array($wgIpEntries)) {
                foreach ($wgIpEntries as $wgi) {
                    if (!empty($wgi['dst-address'])) $allowedHosts[] = $wgi['dst-address'];
                    if (!empty($wgi['dst-host'])) $allowedHosts[] = strtolower($wgi['dst-host']);
                }
            }

            $hasAppDomain = false;
            $hasPayhero = false;
            foreach ($allowedHosts as $h) {
                if (str_contains($h, 'hsmart.co.ke') || str_contains($h, '213.199.49.216')) $hasAppDomain = true;
                if (str_contains($h, 'payhero') || str_contains($h, 'safaricom')) $hasPayhero = true;
            }

            if ($hasAppDomain && $hasPayhero) {
                $checkF['passed'] = true;
                $checkF['detail'] = "Walled garden configured with HSmart, PayHero, and Safaricom bypasses.";
            } else {
                $checkF['detail'] = "Walled garden is missing required domains. Unauthenticated phones cannot reach payment gateways.";
                $checkF['fix'] = "Add walled garden rules for *.hsmart.co.ke, 213.199.49.216, *.payhero.co.ke, *.safaricom.co.ke.";
            }
        } catch (\Throwable $e) {
            $checkF['detail'] = 'Error checking walled garden: ' . $e->getMessage();
            $checkF['fix'] = 'Configure walled garden in /ip hotspot walled-garden.';
        }
        $this->healthChecks['f'] = $checkF;

        // g) DNS works on router (allow-remote-requests=yes)
        $checkG = ['id' => 'dns', 'title' => 'Router DNS service', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $dns = $api->executeCommand('/ip/dns/print');
            if (is_array($dns) && !empty($dns[0])) {
                $d = $dns[0];
                $remote = ($d['allow-remote-requests'] ?? 'false') === 'true';
                $servers = $d['servers'] ?? '';
                if ($remote) {
                    $checkG['passed'] = true;
                    $checkG['detail'] = "DNS server is accepting remote requests with upstream servers ({$servers}).";
                } else {
                    $checkG['detail'] = "DNS allow-remote-requests is disabled. Phones cannot resolve captive portal domain.";
                    $checkG['fix'] = "Set /ip dns set allow-remote-requests=yes servers=8.8.8.8,1.1.1.1.";
                }
            } else {
                $checkG['detail'] = "Could not read router DNS settings.";
                $checkG['fix'] = "Verify DNS in /ip dns.";
            }
        } catch (\Throwable $e) {
            $checkG['detail'] = 'Error checking DNS: ' . $e->getMessage();
            $checkG['fix'] = 'Verify DNS in /ip dns.';
        }
        $this->healthChecks['g'] = $checkG;

        // h) Active hosts & Tenda router-mode detection
        $checkH = ['id' => 'hosts', 'title' => 'Wi-Fi AP & Client mode detection', 'passed' => true, 'detail' => '', 'fix' => ''];
        try {
            $hosts = $api->executeCommand('/ip/hotspot/host/print');
            $leases = $api->executeCommand('/ip/dhcp-server/lease/print');

            $tendaDetected = false;
            $tendaMac = '';
            $tendaIp = '';

            if (is_array($leases)) {
                foreach ($leases as $ls) {
                    $hName = strtolower($ls['host-name'] ?? '');
                    $mac = strtoupper($ls['mac-address'] ?? '');
                    if (str_contains($hName, 'tenda') || str_starts_with($mac, '9E:04:4B') || str_starts_with($mac, 'C8:3A:35') || str_starts_with($mac, '04:95:E6') || str_starts_with($mac, 'D8:32:14') || str_starts_with($mac, '50:2B:73') || str_starts_with($mac, 'CC:2D:21')) {
                        $tendaDetected = true;
                        $tendaMac = $mac;
                        $tendaIp = $ls['address'] ?? ($ls['active-address'] ?? '10.5.50.250');
                        break;
                    }
                }
            }

            $hostCount = is_array($hosts) ? count($hosts) : 0;
            if ($tendaDetected && $hostCount <= 2) {
                $checkH['passed'] = false;
                $checkH['detail'] = "Tenda router ({$tendaMac}) is operating in Router Mode (performing NAT behind {$tendaIp}). Individual phone devices are hidden!";
                $checkH['fix'] = "Switch the Tenda into Access Point (AP) mode: move cable from Tenda WAN port to LAN port, and disable DHCP on the Tenda.";

                $this->tendaWarning = [
                    'mac' => $tendaMac,
                    'ip'  => $tendaIp,
                    'steps' => [
                        '1. Unplug the network cable from the Tenda WAN port (blue or with globe icon).',
                        '2. Plug the cable into any Tenda LAN port (numbered 1, 2, or 3).',
                        '3. Connect your laptop or phone to the Tenda Wi-Fi and open http://192.168.0.1 in your browser.',
                        '4. In the Tenda settings under Administration > LAN Settings, toggle DHCP Server to OFF (Disabled).',
                        '5. Reboot the Tenda. Customer phones will now get individual IP addresses directly from MikroTik and the captive portal sign-in popup will appear automatically!',
                    ],
                ];
            } else {
                $checkH['detail'] = "Detected {$hostCount} active host(s) on Hotspot. Clients are communicating directly with MikroTik.";
            }
        } catch (\Throwable $e) {
            $checkH['detail'] = 'Active host inspection: ' . $e->getMessage();
        }
        $this->healthChecks['h'] = $checkH;

        // i) Portal login page (login.html) uploaded
        $checkI = ['id' => 'login_page', 'title' => 'Captive portal login template (login.html)', 'passed' => false, 'detail' => '', 'fix' => ''];
        try {
            $files = $api->executeCommand('/file/print');
            $hasLoginHtml = false;
            if (is_array($files)) {
                foreach ($files as $f) {
                    $fName = $f['name'] ?? '';
                    if (str_contains($fName, 'hotspot/login.html') || $fName === 'login.html') {
                        $hasLoginHtml = true;
                        break;
                    }
                }
            }
            if ($hasLoginHtml) {
                $checkI['passed'] = true;
                $checkI['detail'] = "Branded 'hotspot/login.html' template is installed on router storage.";
            } else {
                $checkI['detail'] = "No 'hotspot/login.html' file on router. Router will show default MikroTik page or fail.";
                $checkI['fix'] = "Click 'Fix automatically' or 'Push captive portal' to upload the branded portal.";
            }
        } catch (\Throwable $e) {
            $checkI['detail'] = 'Error checking storage files: ' . $e->getMessage();
            $checkI['fix'] = 'Upload login.html to hotspot directory.';
        }
        $this->healthChecks['i'] = $checkI;

        // Count totals
        $this->healthPassedCount = 0;
        $this->healthFailedCount = 0;
        foreach ($this->healthChecks as $c) {
            if ($c['passed']) {
                $this->healthPassedCount++;
            } else {
                $this->healthFailedCount++;
            }
        }

        $this->isRunningHealthCheck = false;
    }

    public function openFixConfirmation(): void
    {
        $this->pendingFixes = [];
        foreach ($this->healthChecks as $key => $check) {
            if (!$check['passed'] && $key !== 'h') { // 'h' is physical Tenda rewiring
                $this->pendingFixes[] = [
                    'key'    => $key,
                    'title'  => $check['title'],
                    'action' => $check['fix'],
                ];
            }
        }

        $this->showFixConfirmationModal = true;
    }

    public function closeFixConfirmation(): void
    {
        $this->showFixConfirmationModal = false;
        $this->pendingFixes = [];
    }

    public function applyAutomaticFix(): void
    {
        if (!$this->healthRouterId) return;

        $this->isFixing = true;
        $router = Router::where('id', $this->healthRouterId)->first();
        if (!$router) {
            $this->isFixing = false;
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

        $roles = is_array($router->port_roles) ? $router->port_roles : [];
        $hsPort = $router->lan_interface ?: ($roles['hotspot'] ?? 'ether2');

        try {
            $api = new MikrotikApiService($host, $port, $user, $pass, 8);

            // Safety guarantee: NEVER touch WAN port or WireGuard interface
            // 1. Ensure hotspot port is enabled
            try {
                $ifaces = $api->executeCommand('/interface/print');
                if (is_array($ifaces)) {
                    foreach ($ifaces as $if) {
                        if (($if['name'] ?? '') === $hsPort && ($if['disabled'] ?? 'false') === 'true') {
                            $api->executeCommand('/interface/set', ['disabled' => 'no'], ['.id' => $if['.id']]);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // 2. IP Address on Hotspot port
            try {
                $addrs = $api->executeCommand('/ip/address/print');
                $hasHsAddr = false;
                if (is_array($addrs)) {
                    foreach ($addrs as $ad) {
                        if (($ad['interface'] ?? '') === $hsPort && str_contains($ad['address'] ?? '', '10.5.50.')) {
                            $hasHsAddr = true;
                            break;
                        }
                    }
                }
                if (!$hasHsAddr) {
                    $api->executeCommand('/ip/address/add', ['address' => '10.5.50.1/24', 'interface' => $hsPort, 'comment' => 'hsmart-hotspot']);
                }
            } catch (\Throwable $e) {}

            // 3. IP Pool
            try {
                $pools = $api->executeCommand('/ip/pool/print');
                $hasPool = false;
                if (is_array($pools)) {
                    foreach ($pools as $p) {
                        if (($p['name'] ?? '') === 'hs-pool-10') {
                            $hasPool = true;
                            break;
                        }
                    }
                }
                if (!$hasPool) {
                    $api->executeCommand('/ip/pool/add', ['name' => 'hs-pool-10', 'ranges' => '10.5.50.10-10.5.50.254']);
                }
            } catch (\Throwable $e) {}

            // 4. DHCP Network & Server
            try {
                $dnets = $api->executeCommand('/ip/dhcp-server/network/print');
                $hasDnet = false;
                if (is_array($dnets)) {
                    foreach ($dnets as $dn) {
                        if (($dn['address'] ?? '') === '10.5.50.0/24') {
                            $hasDnet = true;
                            break;
                        }
                    }
                }
                if (!$hasDnet) {
                    $api->executeCommand('/ip/dhcp-server/network/add', ['address' => '10.5.50.0/24', 'gateway' => '10.5.50.1', 'dns-server' => '10.5.50.1,8.8.8.8', 'comment' => 'hsmart-hotspot']);
                }

                $dhcps = $api->executeCommand('/ip/dhcp-server/print');
                $hasDhcp = false;
                if (is_array($dhcps)) {
                    foreach ($dhcps as $d) {
                        if (($d['name'] ?? '') === 'hs-dhcp-10' || ($d['interface'] ?? '') === $hsPort) {
                            $api->executeCommand('/ip/dhcp-server/set', ['interface' => $hsPort, 'address-pool' => 'hs-pool-10', 'disabled' => 'no', 'lease-time' => '1h'], ['.id' => $d['.id']]);
                            $hasDhcp = true;
                            break;
                        }
                    }
                }
                if (!$hasDhcp) {
                    $api->executeCommand('/ip/dhcp-server/add', ['name' => 'hs-dhcp-10', 'interface' => $hsPort, 'address-pool' => 'hs-pool-10', 'lease-time' => '1h', 'disabled' => 'no']);
                }
            } catch (\Throwable $e) {}

            // 5. DNS allow-remote-requests
            try {
                $api->executeCommand('/ip/dns/set', ['allow-remote-requests' => 'yes', 'servers' => '8.8.8.8,1.1.1.1']);
            } catch (\Throwable $e) {}

            // 6. NAT Masquerade
            try {
                $nats = $api->executeCommand('/ip/firewall/nat/print');
                $hasNat = false;
                if (is_array($nats)) {
                    foreach ($nats as $n) {
                        if (($n['action'] ?? '') === 'masquerade' && (($n['src-address'] ?? '') === '10.5.50.0/24' || ($n['comment'] ?? '') === 'hsmart-hotspot-masq')) {
                            $hasNat = true;
                            break;
                        }
                    }
                }
                if (!$hasNat) {
                    $api->executeCommand('/ip/firewall/nat/add', ['chain' => 'srcnat', 'action' => 'masquerade', 'src-address' => '10.5.50.0/24', 'comment' => 'hsmart-hotspot-masq']);
                }
            } catch (\Throwable $e) {}

            // 7. Hotspot Profile & Server
            try {
                $hsprofName = 'hsmart-hsprof';
                $profs = $api->executeCommand('/ip/hotspot/profile/print');
                $hasProf = false;
                if (is_array($profs)) {
                    foreach ($profs as $pr) {
                        if (($pr['name'] ?? '') === $hsprofName) {
                            $api->executeCommand('/ip/hotspot/profile/set', [
                                'hotspot-address' => '10.5.50.1',
                                'dns-name'        => 'wifi.hsmart.co.ke',
                                'html-directory'  => 'hotspot',
                                'login-by'        => 'http-chap,http-pap,cookie',
                            ], ['.id' => $pr['.id']]);
                            $hasProf = true;
                            break;
                        }
                    }
                }
                if (!$hasProf) {
                    $api->executeCommand('/ip/hotspot/profile/add', [
                        'name'            => $hsprofName,
                        'hotspot-address' => '10.5.50.1',
                        'dns-name'        => 'wifi.hsmart.co.ke',
                        'html-directory'  => 'hotspot',
                        'login-by'        => 'http-chap,http-pap,cookie',
                    ]);
                }

                $hss = $api->executeCommand('/ip/hotspot/print');
                $hasHs = false;
                if (is_array($hss)) {
                    foreach ($hss as $h) {
                        if (($h['name'] ?? '') === 'hsmart-hotspot' || ($h['interface'] ?? '') === $hsPort) {
                            $api->executeCommand('/ip/hotspot/set', [
                                'interface'    => $hsPort,
                                'profile'      => $hsprofName,
                                'address-pool' => 'hs-pool-10',
                                'disabled'     => 'no',
                            ], ['.id' => $h['.id']]);
                            $hasHs = true;
                            break;
                        }
                    }
                }
                if (!$hasHs) {
                    $api->executeCommand('/ip/hotspot/add', [
                        'name'         => 'hsmart-hotspot',
                        'interface'    => $hsPort,
                        'profile'      => $hsprofName,
                        'address-pool' => 'hs-pool-10',
                        'disabled'     => 'no',
                    ]);
                }
            } catch (\Throwable $e) {}

            // 8. Walled Garden Entries
            try {
                $wgHosts = [
                    '*.hsmart.co.ke',
                    'isp.hsmart.co.ke',
                    '*.payhero.co.ke',
                    'api.payhero.co.ke',
                    '*.safaricom.co.ke',
                ];
                $existingWg = $api->executeCommand('/ip/hotspot/walled-garden/print');
                $existingHosts = [];
                if (is_array($existingWg)) {
                    foreach ($existingWg as $w) {
                        if (!empty($w['dst-host'])) $existingHosts[] = strtolower($w['dst-host']);
                    }
                }
                foreach ($wgHosts as $tgt) {
                    if (!in_array(strtolower($tgt), $existingHosts)) {
                        $api->executeCommand('/ip/hotspot/walled-garden/add', ['dst-host' => $tgt, 'action' => 'allow']);
                    }
                }

                // Walled Garden IP for server
                $existingWgIp = $api->executeCommand('/ip/hotspot/walled-garden/ip/print');
                $hasServerIp = false;
                if (is_array($existingWgIp)) {
                    foreach ($existingWgIp as $wi) {
                        if (($wi['dst-address'] ?? '') === '213.199.49.216') {
                            $hasServerIp = true;
                            break;
                        }
                    }
                }
                if (!$hasServerIp) {
                    $api->executeCommand('/ip/hotspot/walled-garden/ip/add', ['dst-address' => '213.199.49.216', 'action' => 'accept']);
                }
            } catch (\Throwable $e) {}

            // 9. Upload branded captive portal login.html
            try {
                $loginHtml = $this->buildRouterLoginHtml($router);
                $api->executeCommand('/file/add', [
                    'name'     => 'hotspot/login.html',
                    'contents' => $loginHtml,
                ]);
            } catch (\Throwable $e) {}

            session()->flash('success', "Hotspot settings and captive portal applied automatically to {$router->name}!");
            $this->dispatch('show-toast', ['type' => 'success', 'message' => "Hotspot configured successfully!"]);
        } catch (\Throwable $e) {
            session()->flash('error', "Automatic fix failed: " . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => "Automatic fix failed: " . $e->getMessage()]);
        }

        $this->isFixing = false;
        $this->showFixConfirmationModal = false;
        $this->runHotspotCheck();
    }

    public function openPortalModal(int $routerId): void
    {
        $this->portalRouterId = $routerId;
        $router = Router::where('id', $routerId)->first();
        $this->portalRouterName = $router ? ($router->name ?: $router->router_name) : 'Router';
        
        $portalUrl = "https://isp.hsmart.co.ke/portal?router={$routerId}";
        $this->portalPreviewUrl = $portalUrl;
        $this->portalQrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($portalUrl);
        $this->showPortalModal = true;
    }

    public function closePortalModal(): void
    {
        $this->showPortalModal = false;
        $this->portalRouterId = null;
    }

    public function pushCaptivePortal(): void
    {
        if (!$this->portalRouterId) return;

        $this->isPushingPortal = true;
        $router = Router::where('id', $this->portalRouterId)->first();
        if (!$router) {
            $this->isPushingPortal = false;
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
            $api = new MikrotikApiService($host, $port, $user, $pass, 6);
            $loginHtml = $this->buildRouterLoginHtml($router);
            $api->executeCommand('/file/add', [
                'name'     => 'hotspot/login.html',
                'contents' => $loginHtml,
            ]);

            session()->flash('success', "Branded captive portal pushed to {$router->name} successfully!");
            $this->dispatch('show-toast', ['type' => 'success', 'message' => "Portal uploaded to {$router->name}!"]);
        } catch (\Throwable $e) {
            session()->flash('error', "Push failed: " . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => "Push failed: " . $e->getMessage()]);
        }

        $this->isPushingPortal = false;
    }

    protected function buildRouterLoginHtml(Router $router): string
    {
        $tenantId = $router->tenant_id ?: $this->getTenantId();
        $brandName = 'HSmart WiFi';
        $supportPhone = '0700 000 000';
        try {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant && !empty($tenant->name)) $brandName = $tenant->name;
            if ($tenant && !empty($tenant->phone)) $supportPhone = $tenant->phone;

            if (Schema::hasTable('isp_portal_settings')) {
                $ps = DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->first();
                if ($ps && !empty($ps->brand_name)) $brandName = $ps->brand_name;
                if ($ps && !empty($ps->support_phone)) $supportPhone = $ps->support_phone;
            }
        } catch (\Throwable $e) {}

        $portalUrl = "https://isp.hsmart.co.ke/portal?router={$router->id}";

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{$brandName} &mdash; Hotspot Login</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { background: #f8fafc; color: #0f172a; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; max-width: 420px; width: 100%; padding: 28px 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .logo-wrap { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .logo-tile { width: 56px; height: 56px; border-radius: 14px; background: #F57C00; display: flex; align-items: center; justify-content: center; color: #fff; }
        .brand-title { font-size: 20px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 4px; }
        .brand-sub { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 24px; }
        .error-box { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .form-group { margin-bottom: 16px; }
        .label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .input { width: 100%; height: 46px; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 0 14px; font-size: 16px; font-weight: 600; text-align: center; letter-spacing: 2px; color: #0f172a; outline: none; transition: border-color 0.2s; text-transform: uppercase; }
        .input:focus { border-color: #F57C00; box-shadow: 0 0 0 3px rgba(245, 124, 0, 0.15); }
        .btn-submit { width: 100%; height: 46px; border: none; border-radius: 10px; background: #F57C00; color: #ffffff; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: #e65100; }
        .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: #94a3b8; font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #e2e8f0; }
        .divider span { padding: 0 10px; }
        .btn-mpesa { width: 100%; height: 44px; border: 1.5px solid #16a34a; border-radius: 10px; background: #f0fdf4; color: #15803d; font-size: 14px; font-weight: 700; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-mpesa:hover { background: #dcfce7; }
        .footer { text-align: center; margin-top: 24px; font-size: 12px; color: #94a3b8; }
        .footer strong { color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <div class="logo-tile">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                    <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                    <line x1="12" y1="20" x2="12.01" y2="20"></line>
                </svg>
            </div>
        </div>

        <h1 class="brand-title">\$(identity)</h1>
        <p class="brand-sub">Connect to high-speed internet</p>

        \$(if error)
        <div class="error-box">\$(error)</div>
        \$(endif)

        <form name="login" action="\$(link-login-only)" method="post">
            <input type="hidden" name="dst" value="\$(link-orig)" />
            <input type="hidden" name="popup" value="true" />

            <div class="form-group">
                <label class="label">Voucher / Access Code</label>
                <input class="input" type="text" name="username" placeholder="e.g. HS7890" value="\$(username)" required autofocus autocapitalize="characters" oninput="this.value = this.value.toUpperCase(); document.forms['login'].elements['password'].value = this.value;" />
                <input type="hidden" name="password" value="\$(username)" />
            </div>

            <button type="submit" class="btn-submit">
                <span>Connect to Internet</span>
            </button>
        </form>

        <div class="divider">
            <span>Don't have a voucher?</span>
        </div>

        <a href="{$portalUrl}" class="btn-mpesa" target="_blank">
            <span>Buy Package via M-Pesa</span>
        </a>

        <div class="footer">
            <p>Helpline: <strong>{$supportPhone}</strong></p>
            <p style="margin-top: 4px;">Powered by {$brandName}</p>
        </div>
    </div>
</body>
</html>
HTML;
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
            'showHealthModal'          => $this->showHealthModal,
            'healthRouterId'           => $this->healthRouterId,
            'healthRouterName'         => $this->healthRouterName,
            'healthChecks'             => $this->healthChecks,
            'tendaWarning'             => $this->tendaWarning,
            'healthPassedCount'        => $this->healthPassedCount,
            'healthFailedCount'        => $this->healthFailedCount,
            'isRunningHealthCheck'     => $this->isRunningHealthCheck,
            'showFixConfirmationModal' => $this->showFixConfirmationModal,
            'pendingFixes'             => $this->pendingFixes,
            'isFixing'                 => $this->isFixing,
            'showPortalModal'          => $this->showPortalModal,
            'portalRouterId'           => $this->portalRouterId,
            'portalRouterName'         => $this->portalRouterName,
            'portalPreviewUrl'         => $this->portalPreviewUrl,
            'portalQrUrl'              => $this->portalQrUrl,
            'isPushingPortal'          => $this->isPushingPortal,
        ])->layout('layouts.app');
    }
}
