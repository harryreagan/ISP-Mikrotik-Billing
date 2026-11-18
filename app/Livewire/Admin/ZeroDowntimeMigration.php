<?php

namespace App\Livewire\Admin;

use App\Models\Router;
use App\Models\Tenant;
use App\Services\Migration\ZeroDowntimeMigrationService;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;
use Livewire\WithFileUploads;

class ZeroDowntimeMigration extends Component
{
    use WithFileUploads;

    public string $activeTab = 'live'; // 'live', 'file', 'history'

    // Live Migration Form State
    public ?int $selectedRouterId = null;
    public string $customHost = '';
    public int $customPort = 8728;
    public string $customUser = 'admin';
    public string $customPassword = '';
    public int $targetTenantId = 1;

    // Scan & Discovery State
    public bool $isScanning = false;
    public ?array $scanResults = null;
    public ?string $scanError = null;

    // Migration Execution State
    public bool $isMigrating = false;
    public ?array $migrationResults = null;
    public ?string $migrationError = null;

    // File Upload State
    public $rscFile;
    public ?array $fileImportResults = null;

    public function mount()
    {
        $tenantId = auth()->user()?->tenant_id ?: 1;
        $this->targetTenantId = (int) $tenantId;

        $firstRouter = Router::withoutGlobalScopes()->where('status', 'online')->first();
        if ($firstRouter) {
            $this->selectedRouterId = $firstRouter->id;
            $this->populateRouterCredentials($firstRouter);
        }
    }

    public function updatedSelectedRouterId($val)
    {
        if ($val) {
            $r = Router::withoutGlobalScopes()->find($val);
            if ($r) {
                $this->populateRouterCredentials($r);
            }
        }
    }

    protected function populateRouterCredentials(Router $r): void
    {
        $this->customHost = (string) ($r->vpn_ip ?: $r->ip);
        $this->customPort = (int) ($r->api_port ?: 8728);
        $this->customUser = (string) ($r->api_user ?: ($r->username ?: 'admin'));
        try {
            $this->customPassword = Crypt::decryptString($r->api_password ?: $r->password);
        } catch (\Throwable $e) {
            $this->customPassword = (string) ($r->api_password ?: $r->password);
        }
    }

    public function scanRouter()
    {
        $this->reset(['scanResults', 'scanError', 'migrationResults', 'migrationError']);
        $this->isScanning = true;

        try {
            $service = app(ZeroDowntimeMigrationService::class);
            $this->scanResults = $service->scanRouter(
                $this->customHost,
                $this->customPort,
                $this->customUser,
                $this->customPassword
            );
        } catch (\Throwable $e) {
            $this->scanError = "Router scan failed: " . $e->getMessage();
        } finally {
            $this->isScanning = false;
        }
    }

    public function executeMigration()
    {
        $this->reset(['migrationResults', 'migrationError']);
        $this->isMigrating = true;

        try {
            $service = app(ZeroDowntimeMigrationService::class);
            $routerId = $this->selectedRouterId ?: Router::firstOrCreate([
                'name' => 'Imported-' . $this->customHost,
            ], [
                'tenant_id' => $this->targetTenantId,
                'ip'        => $this->customHost,
                'api_port'  => $this->customPort,
                'api_user'  => $this->customUser,
                'status'    => 'online',
            ])->id;

            $this->migrationResults = $service->migrateFromRouter(
                $this->targetTenantId,
                $routerId,
                $this->customHost,
                $this->customPort,
                $this->customUser,
                $this->customPassword
            );

            session()->flash('success', $this->migrationResults['message'] ?? 'Migration completed successfully!');
        } catch (\Throwable $e) {
            $this->migrationError = "Migration failed: " . $e->getMessage();
        } finally {
            $this->isMigrating = false;
        }
    }

    public function importFile()
    {
        $this->validate([
            'rscFile' => 'required|file|max:10240', // 10MB max
        ]);

        $content = file_get_contents($this->rscFile->getRealPath());
        $service = app(ZeroDowntimeMigrationService::class);

        $routerId = $this->selectedRouterId ?: (Router::first()?->id ?? 1);

        try {
            $this->fileImportResults = $service->importRscFile(
                $this->targetTenantId,
                $routerId,
                $content
            );
            session()->flash('success', "Imported {$this->fileImportResults['migrated_pppoe']} PPPoE subscribers and {$this->fileImportResults['migrated_hotspot']} Hotspot accounts without downtime!");
        } catch (\Throwable $e) {
            session()->flash('error', "Import error: " . $e->getMessage());
        }
    }

    public function render()
    {
        $routers = Router::withoutGlobalScopes()->get();
        $tenants = Tenant::where('is_active', 1)->get();

        return view('livewire.admin.zero-downtime-migration', [
            'routers' => $routers,
            'tenants' => $tenants,
        ])->layout('layouts.app');
    }
}
