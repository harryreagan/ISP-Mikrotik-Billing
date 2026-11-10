<?php

namespace App\Livewire\Admin;

use App\Models\CustomersInfo;
use App\Models\PackageList;
use App\Models\Router;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class PackagesManager extends Component
{
    use WithPagination;

    // Filters & Search
    public string $tab = 'all'; // 'all', 'hotspot', 'pppoe'
    public string $search = '';

    // Modal state
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $type = 'hotspot'; // 'hotspot' or 'pppoe'
    public $price = 50;
    public $speed_download = 10;
    public string $speed_download_unit = 'Mbps';
    public $speed_upload = 5;
    public string $speed_upload_unit = 'Mbps';
    public string $burst_speed = '';
    public $validity_value = 24;
    public string $validity_unit = 'hours';
    public bool $unlimited_data = true;
    public $data_limit_mb = null;
    public int $shared_users = 1;
    public array $selected_routers = []; // router IDs or ['all']
    public bool $show_public = true;
    public int $sort_order = 0;
    public string $description = '';
    public bool $is_active = true;

    // Feedback
    public ?string $feedbackMessage = null;
    public string $feedbackType = 'success';

    protected function rules(): array
    {
        return [
            'name'                => 'required|string|min:2|max:100',
            'type'                => 'required|in:hotspot,pppoe',
            'price'               => 'required|numeric|min:0',
            'speed_download'      => 'required|numeric|min:1',
            'speed_download_unit' => 'required|in:Mbps,Kbps',
            'speed_upload'        => 'required|numeric|min:1',
            'speed_upload_unit'   => 'required|in:Mbps,Kbps',
            'validity_value'      => 'required|integer|min:1',
            'validity_unit'       => 'required|in:minutes,hours,days,months',
            'shared_users'        => 'required|integer|min:1|max:50',
            'sort_order'          => 'nullable|integer',
            'description'         => 'nullable|string|max:500',
        ];
    }

    public function mount()
    {
        if (! hasAccess(['Super Admin', 'Admin', 'Manager', 'Product Manager'], ['package-setup', 'all-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function setTab(string $tab)
    {
        $this->tab = in_array($tab, ['all', 'hotspot', 'pppoe']) ? $tab : 'all';
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->reset([
            'editingId', 'name', 'burst_speed', 'description'
        ]);
        $this->type = 'hotspot';
        $this->price = 50;
        $this->speed_download = 10;
        $this->speed_download_unit = 'Mbps';
        $this->speed_upload = 5;
        $this->speed_upload_unit = 'Mbps';
        $this->validity_value = 24;
        $this->validity_unit = 'hours';
        $this->unlimited_data = true;
        $this->data_limit_mb = null;
        $this->shared_users = 1;
        $this->selected_routers = [];
        $this->show_public = true;
        $this->is_active = true;
        $this->sort_order = 0;

        $this->showModal = true;
    }

    protected function resolveTenantId(): int
    {
        $user = auth()->user();
        $tenantId = $user?->tenant_id ?: (session('tenant_id') ?: session('impersonated_tenant_id'));
        if (!$tenantId || !\Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenantId)->exists()) {
            $tenantId = \Illuminate\Support\Facades\DB::table('tenants')->where('is_active', 1)->value('id')
                     ?: \Illuminate\Support\Facades\DB::table('tenants')->value('id')
                     ?: 1;
        }
        return (int) $tenantId;
    }

    public function openEditModal(int $id)
    {
        $this->resetValidation();
        $tenantId = $this->resolveTenantId();

        $pkg = PackageList::forTenant($tenantId)->findOrFail($id);
        $this->editingId           = $pkg->id;
        $this->name                = $pkg->package;
        $this->type                = $pkg->type ?: 'hotspot';
        $this->price               = (float) $pkg->price;
        $this->speed_download      = $pkg->speed_download ?: 10;
        $this->speed_download_unit = $pkg->speed_download_unit ?: 'Mbps';
        $this->speed_upload        = $pkg->speed_upload ?: 5;
        $this->speed_upload_unit   = $pkg->speed_upload_unit ?: 'Mbps';
        $this->burst_speed         = $pkg->burst_speed ?: '';
        $this->validity_value      = $pkg->validity_value ?: ($pkg->duration_value ?: 24);
        $this->validity_unit       = $pkg->validity_unit ?: ($pkg->duration_unit ?: 'hours');
        $this->unlimited_data      = empty($pkg->data_limit_mb) && empty($pkg->data_cap_mb);
        $this->data_limit_mb       = $pkg->data_limit_mb ?: $pkg->data_cap_mb;
        $this->shared_users        = $pkg->shared_users ?: 1;
        $this->show_public         = (bool) ($pkg->show_public ?? $pkg->show_on_site);
        $this->sort_order          = (int) $pkg->sort_order;
        $this->description         = $pkg->description ?: '';
        $this->is_active           = (bool) ($pkg->is_active ?? true);

        $this->selected_routers    = $pkg->routers()->pluck('routers.id')->map(fn($id) => (string)$id)->toArray();

        $this->showModal = true;
    }

    public function savePackage()
    {
        $this->validate();

        $tenantId = $this->resolveTenantId();

        $rateLimit = "{$this->speed_upload}" . ($this->speed_upload_unit === 'Kbps' ? 'k' : 'M') . '/' .
                     "{$this->speed_download}" . ($this->speed_download_unit === 'Kbps' ? 'k' : 'M');

        $data = [
            'tenant_id'           => $tenantId,
            'package'             => $this->name,
            'type'                => $this->type,
            'price'               => $this->price,
            'speed'               => "{$this->speed_download} {$this->speed_download_unit}",
            'speed_download'      => $this->speed_download,
            'speed_download_unit' => $this->speed_download_unit,
            'speed_upload'        => $this->speed_upload,
            'speed_upload_unit'   => $this->speed_upload_unit,
            'burst_speed'         => $this->burst_speed ?: null,
            'validity_value'      => $this->validity_value,
            'validity_unit'       => $this->validity_unit,
            'duration_value'      => $this->validity_value,
            'duration_unit'       => $this->validity_unit,
            'data_limit_mb'       => $this->unlimited_data ? null : $this->data_limit_mb,
            'data_cap_mb'         => $this->unlimited_data ? null : $this->data_limit_mb,
            'shared_users'        => $this->type === 'hotspot' ? $this->shared_users : 1,
            'show_public'         => $this->show_public,
            'show_on_site'        => $this->show_public,
            'sort_order'          => $this->sort_order ?: 0,
            'description'         => $this->description,
            'is_active'           => $this->is_active,
            'mikrotik_rate_limit' => $rateLimit,
        ];

        if ($this->editingId) {
            $package = PackageList::forTenant($tenantId)->findOrFail($this->editingId);
            $package->update($data);
        } else {
            $package = PackageList::create($data);
        }

        // Attach Routers
        $routerIds = [];
        if (in_array('all', $this->selected_routers)) {
            $routerIds = Router::forTenant($tenantId)->pluck('id')->toArray();
        } else {
            $routerIds = array_map('intval', array_filter($this->selected_routers, 'is_numeric'));
        }
        $package->routers()->sync($routerIds);

        // Sync Profile to RouterOS
        $syncResults = $this->syncPackageToRouters($package);

        $this->showModal = false;
        $this->feedbackType = 'success';
        $this->feedbackMessage = "Package '{$package->package}' saved successfully. " . $syncResults;
    }

    /**
     * Push package configuration profile to linked MikroTik routers.
     */
    public function syncPackageToRouters(PackageList $package): string
    {
        $routers = $package->routers()->get();
        if ($routers->isEmpty()) {
            return "Saved (no routers linked).";
        }

        $rateLimit = $package->getRateLimitString();
        $sessionTimeout = $package->getSessionTimeoutString();
        $profileName = "hs_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $package->package);

        $syncedCount = 0;
        $failedCount = 0;

        foreach ($routers as $router) {
            try {
                $host = $router->vpn_ip ?: $router->ip;
                if (empty($host) || empty($router->username)) {
                    throw new \Exception("Router IP or credentials not configured.");
                }

                $port = (int) ($router->api_port ?: 8728);
                $password = $router->password;
                if (!empty($router->api_password)) {
                    try {
                        $password = \Illuminate\Support\Facades\Crypt::decryptString($router->api_password);
                    } catch (\Throwable $e) {}
                }

                $api = new MikrotikApiService($host, $port, $router->username, $password, 3);

                if ($package->type === 'hotspot') {
                    // Hotspot User Profile
                    $checkCmd = "/ip hotspot user profile print where name=\"{$profileName}\"";
                    $existing = $api->executeCommand($checkCmd);

                    if (!empty($existing) && is_array($existing) && count($existing) > 0) {
                        $api->executeCommand("/ip hotspot user profile set [find name=\"{$profileName}\"] rate-limit=\"{$rateLimit}\" session-timeout=\"{$sessionTimeout}\" shared-users={$package->shared_users}");
                    } else {
                        $api->executeCommand("/ip hotspot user profile add name=\"{$profileName}\" rate-limit=\"{$rateLimit}\" session-timeout=\"{$sessionTimeout}\" shared-users={$package->shared_users} transparent-proxy=yes status-autorefresh=1m");
                    }
                } else {
                    // PPPoE PPP Profile
                    $checkCmd = "/ppp profile print where name=\"{$profileName}\"";
                    $existing = $api->executeCommand($checkCmd);

                    if (!empty($existing) && is_array($existing) && count($existing) > 0) {
                        $api->executeCommand("/ppp profile set [find name=\"{$profileName}\"] rate-limit=\"{$rateLimit}\"");
                    } else {
                        $api->executeCommand("/ppp profile add name=\"{$profileName}\" rate-limit=\"{$rateLimit}\" local-address=10.0.0.1 remote-address=pppoe-pool");
                    }
                }

                $package->routers()->updateExistingPivot($router->id, [
                    'sync_status' => 'synced',
                    'sync_error'  => null,
                    'synced_at'   => now(),
                ]);
                $syncedCount++;

            } catch (\Throwable $e) {
                Log::warning("[PackageSync] Failed syncing package #{$package->id} to router #{$router->id} ({$router->name}): " . $e->getMessage());
                $package->routers()->updateExistingPivot($router->id, [
                    'sync_status' => 'failed',
                    'sync_error'  => substr($e->getMessage(), 0, 255),
                    'synced_at'   => null,
                ]);
                $failedCount++;
            }
        }

        if ($failedCount > 0) {
            return "Synced to {$syncedCount} router(s), {$failedCount} pending/offline (click Sync to retry).";
        }

        return "Synced to all {$syncedCount} linked router(s).";
    }

    public function retrySync(int $packageId)
    {
        $tenantId = $this->resolveTenantId();
        $package = PackageList::forTenant($tenantId)->findOrFail($packageId);

        $msg = $this->syncPackageToRouters($package);
        $this->feedbackType = 'info';
        $this->feedbackMessage = "Sync Result: " . $msg;
    }

    public function toggleActive(int $id)
    {
        $tenantId = $this->resolveTenantId();
        $pkg = PackageList::forTenant($tenantId)->findOrFail($id);
        $pkg->is_active = !$pkg->is_active;
        $pkg->save();

        $this->feedbackType = 'success';
        $this->feedbackMessage = "Package '{$pkg->package}' is now " . ($pkg->is_active ? 'Active' : 'Inactive') . ".";
    }

    public function duplicatePackage(int $id)
    {
        $tenantId = $this->resolveTenantId();
        $original = PackageList::forTenant($tenantId)->findOrFail($id);

        $copy = $original->replicate([
            'created_at', 'updated_at'
        ]);
        $copy->package = $original->package . ' (Copy)';
        $copy->is_active = false;
        $copy->save();

        // Copy router links
        $copy->routers()->sync($original->routers()->pluck('routers.id')->toArray());

        $this->feedbackType = 'success';
        $this->feedbackMessage = "Package duplicated as '{$copy->package}'.";
    }

    public function deletePackage(int $id)
    {
        $tenantId = $this->resolveTenantId();
        $pkg = PackageList::forTenant($tenantId)->findOrFail($id);

        // Guard: Check active customers
        $hasActiveCustomers = CustomersInfo::where('package_id', $pkg->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveCustomers) {
            $this->feedbackType = 'error';
            $this->feedbackMessage = "Cannot delete '{$pkg->package}' because it has active subscribers. Please deactivate it instead.";
            return;
        }

        $pkg->routers()->detach();
        $pkg->delete();

        $this->feedbackType = 'success';
        $this->feedbackMessage = "Package deleted successfully.";
    }

    public function render()
    {
        $tenantId = $this->resolveTenantId();

        $query = PackageList::forTenant($tenantId)
            ->with(['routers'])
            ->orderBy('sort_order')
            ->orderBy('id', 'desc');

        if ($this->tab === 'hotspot') {
            $query->where('type', 'hotspot');
        } elseif ($this->tab === 'pppoe') {
            $query->where('type', 'pppoe');
        }

        if (!empty($this->search)) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('package', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('speed', 'like', "%{$s}%");
            });
        }

        $packages = $query->paginate(12);
        $allRouters = Router::forTenant($tenantId)->get()->groupBy(fn($r) => $r->location ?: 'Main Location');

        return view('livewire.admin.packages-manager', [
            'packages'   => $packages,
            'allRouters' => $allRouters,
        ])->layout('layouts.app');
    }
}
