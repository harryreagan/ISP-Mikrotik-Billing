<?php

namespace App\Livewire\Admin;

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
     * Poll a specific router for live hardware telemetry (CPU, RAM, Uptime).
     */
    public function pollRouterTelemetry(int $routerId)
    {
        $router = Router::where('id', $routerId)->first();
        if (!$router) {
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
            $api = new MikrotikApiService($host, $port, $user, $pass, 4);
            $res = $api->executeCommand('/system/resource/print');

            if (is_array($res) && !empty($res)) {
                $data = $res[0];
                $cpuLoad = (int) ($data['cpu-load'] ?? 0);
                $freeMem = (int) ($data['free-memory'] ?? 0);
                $totalMem = (int) ($data['total-memory'] ?? 0);
                $memPercent = $totalMem > 0 ? (int) round((($totalMem - $freeMem) / $totalMem) * 100) : 0;
                $uptime = (string) ($data['uptime'] ?? 'unknown');
                $version = (string) ($data['version'] ?? $router->version);
                $board = (string) ($data['board-name'] ?? $router->model);

                $router->updateQuietly([
                    'status'       => 'online',
                    'cpu_load'     => $cpuLoad,
                    'memory_load'  => $memPercent,
                    'uptime'       => $uptime,
                    'version'      => $version,
                    'model'        => $board,
                    'last_seen_at' => now(),
                ]);

                session()->flash('success', "Telemetry refreshed for {$router->name}: CPU {$cpuLoad}%, RAM {$memPercent}%, Uptime {$uptime}.");
            } else {
                session()->flash('warning', "Router {$router->name} did not return telemetry data.");
            }
        } catch (\Throwable $e) {
            session()->flash('error', "Could not reach router {$router->name} at {$host}:{$port} ({$e->getMessage()}).");
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

            session()->flash('success', "Reboot signal sent to {$router->name}. The router will restart and reconnect within 60 seconds.");
        } catch (\Throwable $e) {
            session()->flash('error', "Failed to reboot {$router->name}: " . $e->getMessage());
        }
    }

    /**
     * Open Edit Modal
     */
    public function editRouter(int $routerId)
    {
        $router = Router::where('id', $routerId)->first();
        if (!$router) {
            return;
        }

        $this->editingRouterId = $router->id;
        $this->edit_name = $router->name ?: ($router->router_name ?: '');
        $this->edit_location = $router->location ?: '';
        $this->edit_ip = $router->ip ?: ($router->ip_address ?: '');
        $this->edit_api_port = (int) ($router->api_port ?: 8728);
        $this->edit_username = $router->api_user ?: ($router->username ?: 'admin');
        $this->edit_password = '';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingRouterId = null;
        $this->resetValidation();
    }

    /**
     * Save Router Edits
     */
    public function updateRouter()
    {
        $this->validate();

        $router = Router::where('id', $this->editingRouterId)->first();
        if (!$router) {
            session()->flash('error', 'Router not found.');
            $this->closeEditModal();
            return;
        }

        $data = [
            'name'        => $this->edit_name,
            'router_name' => $this->edit_name,
            'location'    => $this->edit_location,
            'ip'          => $this->edit_ip,
            'ip_address'  => $this->edit_ip,
            'api_port'    => $this->edit_api_port,
            'username'    => $this->edit_username,
            'api_user'    => $this->edit_username,
        ];

        if (!empty($this->edit_password)) {
            $data['password'] = Crypt::encryptString($this->edit_password);
            $data['api_password'] = Crypt::encryptString($this->edit_password);
        }

        $router->update($data);

        session()->flash('success', "Router {$this->edit_name} updated successfully.");
        $this->closeEditModal();
    }

    /**
     * Delete Router safely and free allocated VPN IP.
     */
    public function deleteRouter(int $routerId)
    {
        $router = Router::where('id', $routerId)->first();
        if (!$router) {
            return;
        }

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
                  ->orWhere('vpn_ip', 'like', $term);
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
            $r->subscribers_count = PPPSecrets::where('router_name', $r->name ?: $r->router_name)
                ->where('status', '!=', 'removed')
                ->count();
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
            'routers'       => $routers,
            'totalRouters'  => $totalRouters,
            'onlineRouters' => $onlineRouters,
            'avgCpu'        => $avgCpu,
            'totalClients'  => $totalClients,
        ])->layout('layouts.app');
    }
}

