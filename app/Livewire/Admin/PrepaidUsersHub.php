<?php

namespace App\Livewire\Admin;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\PrepaidUser;
use App\Models\Router;
use App\Scopes\TenantScope;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use App\Services\Sms\IspSmsDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class PrepaidUsersHub extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Page mode driven by route: all, active, expired, online, offline, activate, roaming
    public string $section = 'all';

    // Filters
    public string $typeTab = 'all'; // all, hotspot, static, pppoe
    public string $search = '';
    public string $routerFilter = 'all';
    public int $perPage = 15;

    // Selection for bulk actions
    public array $selectedIds = [];
    public bool $selectAll = false;

    // Modals & Forms
    public bool $showActivateModal = false;
    public bool $showExtendModal = false;
    public bool $showEditModal = false;
    public bool $showSmsModal = false;

    // Manual Activate User fields
    public string $act_username = '';
    public string $act_password = '';
    public string $act_phone = '';
    public string $act_mac = '';
    public string $act_type = 'hotspot';
    public ?int $act_package_id = null;
    public ?int $act_router_id = null;
    public string $act_method = 'Manual Cash';
    public int $act_duration_value = 1;
    public string $act_duration_unit = 'hours';
    public bool $act_fup_enabled = false;
    public ?int $act_fup_limit_mb = null;

    // Extend fields (single or bulk)
    public ?int $extendingUserId = null;
    public bool $isBulkExtend = false;
    public int $extend_value = 1;
    public string $extend_unit = 'hours';

    // Edit user fields
    public ?int $editingUserId = null;
    public string $edit_username = '';
    public string $edit_phone = '';
    public string $edit_mac = '';
    public ?int $edit_package_id = null;
    public ?int $edit_router_id = null;
    public bool $edit_fup_enabled = false;
    public ?int $edit_fup_limit_mb = null;
    public ?string $edit_expires_at = null;

    // SMS modal
    public string $smsMessage = 'Your WiFi pass has expired or needs renewal. Reconnect to WiFi and pay via M-Pesa to continue enjoying high-speed internet!';

    // Roaming setting
    public bool $allowRoaming = true;

    // Feedback
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $routeName = request()->route()?->getName() ?: '';
        $this->section = match ($routeName) {
            'admin.activation.active-users'  => 'active',
            'admin.activation.expired-users' => 'expired',
            'admin.activation.online-users'  => 'online',
            'admin.activation.offline-users' => 'offline',
            'admin.activation.activate-user' => 'activate',
            'admin.activation.roaming-users' => 'roaming',
            default                          => 'all',
        };

        $tenantId = $this->getTenantId();
        $this->loadRoamingSetting($tenantId);

        if ($this->section === 'activate') {
            $this->openActivateModal();
        }
    }

    protected function getTenantId(): int
    {
        $user = auth()->user();
        return (int) ($user?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1)));
    }

    protected function loadRoamingSetting(int $tenantId): void
    {
        try {
            if (Schema::hasTable('isp_portal_settings') && Schema::hasColumn('isp_portal_settings', 'allow_roaming')) {
                $val = DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->value('allow_roaming');
                $this->allowRoaming = $val === null ? true : (bool) $val;
            }
        } catch (\Throwable $e) {
            $this->allowRoaming = true;
        }
    }

    public function toggleAllowRoaming(): void
    {
        $tenantId = $this->getTenantId();
        $this->allowRoaming = !$this->allowRoaming;
        try {
            if (Schema::hasTable('isp_portal_settings') && Schema::hasColumn('isp_portal_settings', 'allow_roaming')) {
                $exists = DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->exists();
                if ($exists) {
                    DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->update([
                        'allow_roaming' => $this->allowRoaming,
                        'updated_at'    => now(),
                    ]);
                } else {
                    DB::table('isp_portal_settings')->insert([
                        'tenant_id'     => $tenantId,
                        'allow_roaming' => $this->allowRoaming,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
            $this->statusMessage = 'Roaming setting updated: Roaming is now ' . ($this->allowRoaming ? 'Enabled' : 'Disabled') . ' for your network.';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed updating roaming setting: ' . $e->getMessage();
        }
    }

    public function setTypeTab(string $tab): void
    {
        $this->typeTab = in_array($tab, ['all', 'hotspot', 'static', 'pppoe'], true) ? $tab : 'all';
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingRouterFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedIds = $this->buildFilteredQuery()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    protected function buildFilteredQuery()
    {
        $tenantId = $this->getTenantId();

        $query = PrepaidUser::withoutGlobalScopes()
            ->with([
                'package' => fn($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId),
                'router' => fn($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId),
                'roamingRouter' => fn($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId),
            ])
            ->where('tenant_id', $tenantId);

        if ($this->typeTab !== 'all') {
            $query->where('type', $this->typeTab);
        }

        if ($this->routerFilter !== 'all' && is_numeric($this->routerFilter)) {
            $rid = (int) $this->routerFilter;
            $query->where(function ($q) use ($rid) {
                $q->where('router_id', $rid)->orWhere('roaming_router_id', $rid);
            });
        }

        // Section filters from left sidebar
        if ($this->section === 'active') {
            $query->whereNotIn('status', ['expired', 'disabled'])
                  ->where(function ($q) {
                      $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                  });
        } elseif ($this->section === 'expired') {
            $query->where(function ($q) {
                $q->where('status', 'expired')
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('expires_at')->where('expires_at', '<=', now());
                  });
            });
        } elseif ($this->section === 'online') {
            $query->where(function ($q) {
                $q->where('is_online', true)->orWhere('status', 'online');
            });
        } elseif ($this->section === 'offline') {
            $query->where('is_online', false)->whereNotIn('status', ['expired', 'disabled']);
        } elseif ($this->section === 'roaming') {
            $query->where('is_roaming', true);
        }

        if (!empty(trim($this->search))) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('username', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('mac_address', 'like', $term)
                  ->orWhere('receipt_code', 'like', $term)
                  ->orWhere('plan_name', 'like', $term);
            });
        }

        return $query->orderByDesc('id');
    }

    // ══════════════════════════════════════════════════════════════════════
    // MANUAL ACTIVATION MODAL
    // ══════════════════════════════════════════════════════════════════════
    public function openActivateModal(): void
    {
        $tenantId = $this->getTenantId();
        $this->resetValidation();
        $this->act_username = 'HS' . strtoupper(Str::random(6));
        $this->act_password = $this->act_username;
        $this->act_phone = '';
        $this->act_mac = '';
        $this->act_type = 'hotspot';
        $this->act_method = 'Manual Cash';
        $this->act_fup_enabled = false;
        $this->act_fup_limit_mb = null;

        $pkg = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'hotspot')->first()
            ?: PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        $this->act_package_id = $pkg?->id;
        if ($pkg) {
            $this->act_duration_value = (int) ($pkg->validity_value ?: ($pkg->duration_value ?: 1));
            $this->act_duration_unit = $pkg->validity_unit ?: ($pkg->duration_unit ?: 'hours');
            $this->act_fup_enabled = !empty($pkg->data_limit_mb);
            $this->act_fup_limit_mb = $pkg->data_limit_mb;
        } else {
            $this->act_duration_value = 1;
            $this->act_duration_unit = 'hours';
        }

        $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('status', 'online')->first()
            ?: Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->first();
        $this->act_router_id = $router?->id;
        $this->showActivateModal = true;
    }

    public function updatedActPackageId($val): void
    {
        $tenantId = $this->getTenantId();
        $pkg = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find((int) $val);
        if ($pkg) {
            $this->act_type = in_array($pkg->type, ['hotspot', 'pppoe', 'static'], true) ? $pkg->type : 'hotspot';
            $this->act_duration_value = (int) ($pkg->validity_value ?: ($pkg->duration_value ?: 1));
            $this->act_duration_unit = $pkg->validity_unit ?: ($pkg->duration_unit ?: 'hours');
            $this->act_fup_enabled = !empty($pkg->data_limit_mb);
            $this->act_fup_limit_mb = $pkg->data_limit_mb;
        }
    }

    public function saveManualActivation(): void
    {
        $this->validate([
            'act_username'       => 'required|string|min:2|max:100',
            'act_password'       => 'nullable|string|max:100',
            'act_type'           => 'required|in:hotspot,pppoe,static',
            'act_router_id'      => 'required|integer',
            'act_duration_value' => 'required|integer|min:1',
            'act_duration_unit'  => 'required|in:minutes,hours,days,months',
        ]);

        $tenantId = $this->getTenantId();
        $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($this->act_router_id);
        $pkg = $this->act_package_id
            ? PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->act_package_id)
            : null;

        $username = trim($this->act_username);
        if ($this->act_type === 'hotspot') {
            $username = strtoupper($username);
        }
        $password = trim($this->act_password ?: $username);
        $phone = function_exists('normalizeKenyanPhone') && !empty($this->act_phone)
            ? (normalizeKenyanPhone($this->act_phone) ?: trim($this->act_phone))
            : trim($this->act_phone);

        $dur = MikrotikDuration::parse($this->act_duration_value, $this->act_duration_unit);
        $startedAt = now();
        $expiresAt = now()->addSeconds($dur['seconds']);
        $amount = (float) ($pkg?->price ?? 0);

        $user = PrepaidUser::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'username'  => $username,
                'type'      => $this->act_type,
            ],
            [
                'password'             => $password,
                'phone'                => $phone ?: null,
                'mac_address'          => $this->act_mac ? strtoupper(trim($this->act_mac)) : null,
                'package_id'           => $pkg?->id,
                'plan_name'            => $pkg?->package ?: null,
                'router_id'            => $router->id,
                'router_name'          => $router->name,
                'method'               => $this->act_method ?: 'Manual',
                'receipt_code'         => $username,
                'amount'               => $amount,
                'status'               => 'offline',
                'is_online'            => false,
                'disconnection_reason' => null,
                'fup_enabled'          => $this->act_fup_enabled,
                'fup_limit_mb'         => $this->act_fup_enabled ? ($this->act_fup_limit_mb ?: $pkg?->data_limit_mb) : null,
                'started_at'           => $startedAt,
                'expires_at'           => $expiresAt,
            ]
        );

        if ($this->act_type === 'hotspot') {
            $sale = HotspotSale::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'voucher_code' => $username],
                [
                    'router_id'        => $router->id,
                    'router_name'      => $router->name,
                    'profile'          => $pkg?->package ?: 'default',
                    'username'         => $phone ?: $username,
                    'phone'            => $phone ?: null,
                    'mac_address'      => $user->mac_address,
                    'amount'           => $amount,
                    'payment_method'   => 'manual',
                    'status'           => 'completed',
                    'duration_minutes' => (int) ceil($dur['seconds'] / 60),
                    'started_at'       => $startedAt,
                    'expires_at'       => $expiresAt,
                    'sale_date'        => now()->timezone('Africa/Nairobi')->toDateString(),
                ]
            );
            $user->update(['hotspot_sale_id' => $sale->id]);
        }

        if ($amount > 0 && !CollectionSummary::withoutGlobalScopes()->where('transaction_id', $username)->exists()) {
            $maxInv = (int) (CollectionSummary::withoutGlobalScopes()->max('invoice_no') ?: 100000);
            CollectionSummary::withoutGlobalScopes()->create([
                'tenant_id'                     => $tenantId,
                'customer_collection_unique_id' => $phone ?: $username,
                'subscriber_name'               => $username . ($phone ? " ({$phone})" : ''),
                'phone'                         => $phone ?: null,
                'mac_address'                   => $user->mac_address,
                'router_id'                     => $router->id,
                'collection_date'               => now(),
                'collection_amount'             => $amount,
                'collected_by'                  => auth()->user()?->email ?? 'Admin',
                'payment_type'                  => $this->act_type === 'hotspot' ? 'hotspot' : 'Cash',
                'service_type'                  => $this->act_type === 'hotspot' ? 'hotspot' : 'pppoe',
                'package_id'                    => $pkg?->id,
                'payment_method'                => 'manual',
                'transaction_id'                => $username,
                'payment_status'                => 'paid',
                'invoice_no'                    => $maxInv + 1,
                'bill_month'                    => now()->format('F Y'),
            ]);
        }

        $routerErr = $this->provisionPrepaidUserOnRouter($user, $router, $pkg, $dur['mikrotik']);
        $this->showActivateModal = false;

        if ($routerErr) {
            $this->statusMessage = "User '{$username}' saved in DB, but router sync reported: {$routerErr}";
        } else {
            $this->statusMessage = "Prepaid user '{$username}' activated and pushed to router '{$router->name}'!";
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // SINGLE & BULK MANAGE ACTIONS (EDIT / EXTEND / ENABLE / DISABLE / DELETE / RECTIFY)
    // ══════════════════════════════════════════════════════════════════════
    public function openEditModal(int $id): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);
        $this->editingUserId = $user->id;
        $this->edit_username = $user->username;
        $this->edit_phone = $user->phone ?: '';
        $this->edit_mac = $user->mac_address ?: '';
        $this->edit_package_id = $user->package_id;
        $this->edit_router_id = $user->router_id;
        $this->edit_fup_enabled = (bool) $user->fup_enabled;
        $this->edit_fup_limit_mb = $user->fup_limit_mb;
        $this->edit_expires_at = $user->expires_at ? $user->expires_at->format('Y-m-d\TH:i') : null;
        $this->showEditModal = true;
    }

    public function saveEditUser(): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($this->editingUserId);

        $pkg = $this->edit_package_id
            ? PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->edit_package_id)
            : null;
        $router = $this->edit_router_id
            ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->edit_router_id)
            : null;

        $expiresAt = $this->edit_expires_at ? Carbon::parse($this->edit_expires_at) : $user->expires_at;

        $user->update([
            'phone'        => trim($this->edit_phone) ?: null,
            'mac_address'  => $this->edit_mac ? strtoupper(trim($this->edit_mac)) : null,
            'package_id'   => $pkg?->id,
            'plan_name'    => $pkg?->package ?: $user->plan_name,
            'router_id'    => $router?->id ?: $user->router_id,
            'router_name'  => $router?->name ?: $user->router_name,
            'fup_enabled'  => $this->edit_fup_enabled,
            'fup_limit_mb' => $this->edit_fup_enabled ? ($this->edit_fup_limit_mb ?: $pkg?->data_limit_mb) : null,
            'expires_at'   => $expiresAt,
        ]);

        if ($router) {
            $remSec = $expiresAt && $expiresAt->isFuture() ? max(60, now()->diffInSeconds($expiresAt)) : 3600;
            $this->provisionPrepaidUserOnRouter($user->fresh(), $router, $pkg, MikrotikDuration::secondsToMikrotik($remSec));
        }

        $this->showEditModal = false;
        $this->statusMessage = "Updated prepaid user '{$user->username}'.";
    }

    public function openExtendModal(?int $id = null): void
    {
        $this->isBulkExtend = ($id === null);
        $this->extendingUserId = $id;
        $this->extend_value = 1;
        $this->extend_unit = 'hours';
        $this->showExtendModal = true;
    }

    public function saveExtend(): void
    {
        $this->validate([
            'extend_value' => 'required|integer|min:1',
            'extend_unit'  => 'required|in:minutes,hours,days,months',
        ]);

        $tenantId = $this->getTenantId();
        $ids = $this->isBulkExtend
            ? array_map('intval', $this->selectedIds)
            : [$this->extendingUserId];

        $dur = MikrotikDuration::parse($this->extend_value, $this->extend_unit);
        $count = 0;
        $errors = [];

        $users = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get();
        foreach ($users as $user) {
            $base = ($user->expires_at && $user->expires_at->isFuture()) ? $user->expires_at->copy() : now();
            $newExp = $base->addSeconds($dur['seconds']);
            $totalSecsFromStart = max(60, ($user->started_at ? Carbon::parse($user->started_at)->diffInSeconds($newExp) : $dur['seconds']));
            $limitUptime = MikrotikDuration::secondsToMikrotik($totalSecsFromStart);

            $user->update([
                'expires_at'           => $newExp,
                'status'               => $user->is_online ? 'online' : 'offline',
                'disconnection_reason' => null,
            ]);

            if ($user->hotspot_sale_id) {
                HotspotSale::withoutGlobalScopes()->where('id', $user->hotspot_sale_id)->update([
                    'expires_at' => $newExp,
                    'status'     => 'completed',
                ]);
            }
            if ($user->customer_id) {
                $cust = CustomersInfo::withoutGlobalScope(TenantScope::class)->find($user->customer_id);
                if ($cust) {
                    $cust->update(['status' => 'active']);
                    BillingInfo::withoutGlobalScope(TenantScope::class)
                        ->where('customer_bill_unique_id', $cust->customer_unique_id)
                        ->update(['auto_disable_date' => $newExp]);
                }
            }

            $router = $user->router_id ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->router_id) : null;
            $pkg = $user->package_id ? PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->package_id) : null;
            if ($router) {
                $err = $this->provisionPrepaidUserOnRouter($user->fresh(), $router, $pkg, $limitUptime);
                if ($err) {
                    $errors[] = "{$user->username}: {$err}";
                }
            }
            $count++;
        }

        $this->showExtendModal = false;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->statusMessage = "Extended validity by {$this->extend_value} {$this->extend_unit} for {$count} user(s)."
            . (!empty($errors) ? ' Router notes: ' . implode('; ', $errors) : '');
    }

    public function enableUser(int $id): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);

        if ($user->expires_at && $user->expires_at->isPast()) {
            $user->expires_at = now()->addDay();
        }

        $user->update([
            'status'               => 'offline',
            'disconnection_reason' => null,
            'expires_at'           => $user->expires_at,
        ]);

        if ($user->customer_id) {
            CustomersInfo::withoutGlobalScope(TenantScope::class)->where('id', $user->customer_id)->update(['status' => 'active']);
        }
        if ($user->ppp_secret_id) {
            PPPSecrets::withoutGlobalScopes()->where('id', $user->ppp_secret_id)->update(['status' => 'active']);
        }

        $router = $user->router_id ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->router_id) : null;
        $pkg = $user->package_id ? PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->package_id) : null;
        $remSec = $user->expires_at ? max(60, now()->diffInSeconds($user->expires_at)) : 86400;

        $err = $router ? $this->provisionPrepaidUserOnRouter($user->fresh(), $router, $pkg, MikrotikDuration::secondsToMikrotik($remSec)) : null;

        $this->statusMessage = $err
            ? "User '{$user->username}' enabled in DB, router note: {$err}"
            : "User '{$user->username}' enabled on router.";
    }

    public function disableUser(int $id, string $reason = 'disabled'): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);

        $router = $user->router_id ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->router_id) : null;
        $err = $router ? $this->disableUserOnRouter($user, $router) : null;

        if ($user->roaming_router_id && (int) $user->roaming_router_id !== (int) $user->router_id) {
            $roamingR = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->roaming_router_id);
            if ($roamingR) {
                $this->disableUserOnRouter($user, $roamingR);
            }
        }

        $user->update([
            'status'               => 'disabled',
            'is_online'            => false,
            'disconnection_reason' => $reason,
        ]);

        if ($user->customer_id) {
            CustomersInfo::withoutGlobalScope(TenantScope::class)->where('id', $user->customer_id)->update(['status' => 'suspended']);
        }
        if ($user->ppp_secret_id) {
            PPPSecrets::withoutGlobalScopes()->where('id', $user->ppp_secret_id)->update(['status' => 'disabled']);
        }

        $this->statusMessage = $err
            ? "User '{$user->username}' disabled in DB (router note: {$err})."
            : "User '{$user->username}' disabled and active session removed on router.";
    }

    public function deleteUser(int $id): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);
        $uName = $user->username;

        $router = $user->router_id ? Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->router_id) : null;
        if ($router) {
            $this->removeUserFromRouter($user, $router);
        }
        if ($user->roaming_router_id && (int) $user->roaming_router_id !== (int) $user->router_id) {
            $roamingR = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($user->roaming_router_id);
            if ($roamingR) {
                $this->removeUserFromRouter($user, $roamingR);
            }
        }

        if ($user->hotspot_sale_id) {
            HotspotSale::withoutGlobalScopes()->where('id', $user->hotspot_sale_id)->delete();
        }
        if ($user->ppp_secret_id) {
            PPPSecrets::withoutGlobalScopes()->where('id', $user->ppp_secret_id)->delete();
        }

        $user->delete();
        $this->statusMessage = "User '{$uName}' removed from router and database.";
    }

    public function rectifyUser(int $id): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);
        $reason = strtolower((string) ($user->disconnection_reason ?? ''));

        if ($reason === 'expired' || ($user->expires_at && $user->expires_at->isPast())) {
            $this->openExtendModal($user->id);
            return;
        }

        if (str_contains($reason, 'fup') || str_contains($reason, 'data')) {
            $user->update([
                'daily_data_bytes'     => 0,
                'disconnection_reason' => null,
            ]);
            $this->enableUser($user->id);
            $this->statusMessage = "Reset FUP counter and re-enabled '{$user->username}'.";
            return;
        }

        $this->enableUser($user->id);
    }

    public function toggleFup(int $id): void
    {
        $tenantId = $this->getTenantId();
        $user = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id);
        $user->update(['fup_enabled' => !$user->fup_enabled]);
        $this->statusMessage = "FUP " . ($user->fup_enabled ? 'enabled' : 'disabled') . " for '{$user->username}'.";
    }

    // ── Bulk Actions ────────────────────────────────────────────────────────
    public function bulkEnable(): void
    {
        foreach (array_map('intval', $this->selectedIds) as $id) {
            try { $this->enableUser($id); } catch (\Throwable $e) {}
        }
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->statusMessage = 'Selected users enabled and synced to routers.';
    }

    public function bulkDisable(): void
    {
        foreach (array_map('intval', $this->selectedIds) as $id) {
            try { $this->disableUser($id, 'disabled'); } catch (\Throwable $e) {}
        }
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->statusMessage = 'Selected users disabled and kicked on routers.';
    }

    public function bulkDelete(): void
    {
        foreach (array_map('intval', $this->selectedIds) as $id) {
            try { $this->deleteUser($id); } catch (\Throwable $e) {}
        }
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->statusMessage = 'Selected users deleted from routers and database.';
    }

    public function openBulkSmsModal(): void
    {
        if (empty($this->selectedIds)) {
            $this->errorMessage = 'Select at least one user with a phone number to send SMS.';
            return;
        }
        $this->showSmsModal = true;
    }

    public function sendBulkSms(): void
    {
        $tenantId = $this->getTenantId();
        $users = PrepaidUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', $this->selectedIds))
            ->whereNotNull('phone')
            ->get();

        $sentCount = 0;
        foreach ($users as $u) {
            if (empty($u->phone)) continue;
            try {
                $ok = IspSmsDispatcher::sendDirectSms($u->phone, $this->smsMessage, $tenantId);
                if ($ok) {
                    $u->update([
                        'reconnection_sms_sent'    => true,
                        'reconnection_sms_sent_at' => now(),
                    ]);
                    $sentCount++;
                }
            } catch (\Throwable $e) {}
        }

        $this->showSmsModal = false;
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->statusMessage = "Sent reconnection SMS to {$sentCount} subscriber(s).";
    }

    // ══════════════════════════════════════════════════════════════════════
    // ROUTER SYNC & CSV EXPORT
    // ══════════════════════════════════════════════════════════════════════
    public function syncAllFromRouters(): void
    {
        $tenantId = $this->getTenantId();
        $routers = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('status', 'online')->get();
        if ($routers->isEmpty()) {
            $routers = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->get();
        }
        $totalSynced = 0;
        foreach ($routers as $r) {
            $totalSynced += $this->syncSingleRouter($r, $tenantId);
        }
        $this->statusMessage = "Synced {$totalSynced} user(s) across " . $routers->count() . " tenant router(s).";
    }

    public function syncFromRouter(int $routerId): void
    {
        $tenantId = $this->getTenantId();
        $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($routerId);
        $count = $this->syncSingleRouter($router, $tenantId);
        $this->statusMessage = "Synced {$count} user(s) from router '{$router->name}'.";
    }

    protected function syncSingleRouter(Router $router, int $tenantId): int
    {
        $synced = 0;
        try {
            $api = $this->connectRouter($router);
            $hsUsers = $api->executeCommand('/ip/hotspot/user/print') ?: [];
            $hsActive = $api->executeCommand('/ip/hotspot/active/print') ?: [];
            $pppSecrets = $api->executeCommand('/ppp/secret/print') ?: [];
            $pppActive = $api->executeCommand('/ppp/active/print') ?: [];

            $hsActMap = [];
            if (is_array($hsActive)) {
                foreach ($hsActive as $a) {
                    if (!empty($a['user'])) $hsActMap[strtolower(trim($a['user']))] = $a;
                }
            }
            $pppActMap = [];
            if (is_array($pppActive)) {
                foreach ($pppActive as $a) {
                    if (!empty($a['name'])) $pppActMap[strtolower(trim($a['name']))] = $a;
                }
            }

            if (is_array($hsUsers)) {
                foreach ($hsUsers as $hu) {
                    $uName = trim((string) ($hu['name'] ?? ''));
                    if ($uName === '' || strtolower($uName) === 'default-trial') continue;

                    $uKey = strtolower($uName);
                    $act = $hsActMap[$uKey] ?? null;
                    $comment = $hu['comment'] ?? '';
                    $phone = preg_match('/(?:254|0)[17]\d{8}/', $comment, $m) ? $m[0] : null;
                    $expiresAt = preg_match('/exp:([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})/', $comment, $em)
                        ? Carbon::parse($em[1], 'Africa/Nairobi')
                        : null;

                    $pkg = $this->resolvePackageByProfile($tenantId, $hu['profile'] ?? null);
                    $isDisabled = ($hu['disabled'] ?? 'false') === 'true';
                    $isExp = !$isDisabled && $expiresAt && $expiresAt->isPast();

                    PrepaidUser::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'username' => strtoupper($uName), 'type' => 'hotspot'],
                        [
                            'password'         => $hu['password'] ?? strtoupper($uName),
                            'phone'            => $phone,
                            'mac_address'      => !empty($act['mac-address']) ? strtoupper($act['mac-address']) : (!empty($hu['mac-address']) ? strtoupper($hu['mac-address']) : null),
                            'ip_address'       => $act['address'] ?? ($hu['address'] ?? null),
                            'package_id'       => $pkg?->id,
                            'plan_name'        => $pkg?->package ?: ($hu['profile'] ?? null),
                            'router_id'        => $router->id,
                            'router_name'      => $router->name,
                            'method'           => 'Router Sync',
                            'status'           => $isDisabled ? 'disabled' : ($isExp ? 'expired' : ($act ? 'online' : 'offline')),
                            'is_online'        => (bool) $act,
                            'was_connected'    => (bool) $act,
                            'daily_data_bytes' => $act ? ((int) ($act['bytes-in'] ?? 0) + (int) ($act['bytes-out'] ?? 0)) : 0,
                            'last_seen_at'     => $act ? now() : null,
                            'expires_at'       => $expiresAt,
                        ]
                    );
                    $synced++;
                }
            }

            if (is_array($pppSecrets)) {
                foreach ($pppSecrets as $pu) {
                    $uName = trim((string) ($pu['name'] ?? ''));
                    if ($uName === '') continue;

                    $act = $pppActMap[strtolower($uName)] ?? null;
                    $comment = $pu['comment'] ?? '';
                    $phone = preg_match('/(?:254|0)[17]\d{8}/', $comment, $m) ? $m[0] : null;
                    $pkg = $this->resolvePackageByProfile($tenantId, $pu['profile'] ?? null);
                    $isDisabled = ($pu['disabled'] ?? 'false') === 'true';

                    PrepaidUser::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'username' => $uName, 'type' => 'pppoe'],
                        [
                            'password'         => $pu['password'] ?? null,
                            'phone'            => $phone,
                            'mac_address'      => !empty($act['caller-id']) ? strtoupper($act['caller-id']) : (!empty($pu['caller-id']) ? strtoupper($pu['caller-id']) : null),
                            'ip_address'       => $act['address'] ?? ($pu['remote-address'] ?? null),
                            'package_id'       => $pkg?->id,
                            'plan_name'        => $pkg?->package ?: ($pu['profile'] ?? null),
                            'router_id'        => $router->id,
                            'router_name'      => $router->name,
                            'method'           => 'Router Sync',
                            'status'           => $isDisabled ? 'disabled' : ($act ? 'online' : 'offline'),
                            'is_online'        => (bool) $act,
                            'was_connected'    => (bool) $act,
                            'daily_data_bytes' => $act ? ((int) ($act['bytes-in'] ?? 0) + (int) ($act['bytes-out'] ?? 0)) : 0,
                            'last_seen_at'     => $act ? now() : null,
                        ]
                    );
                    $synced++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[PrepaidUsersHub] syncSingleRouter failed on {$router->name}: " . $e->getMessage());
            $this->errorMessage = "Could not connect to router '{$router->name}': " . $e->getMessage();
        }
        return $synced;
    }

    public function exportCsv()
    {
        $rows = $this->buildFilteredQuery()->get();
        $filename = 'prepaid-users-' . $this->section . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Username', 'Phone', 'MAC Address', 'Plan Name', 'Type',
                'Created On', 'Expires On', 'Method', 'Router', 'Roaming Router',
                'Status', 'Last Seen', 'Daily Data Used', 'Was Connected?',
                'Reconnection SMS Sent?', 'Disconnection Reason', 'FUP',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->username,
                    $row->phone,
                    $row->mac_address,
                    $row->package?->name ?? ($row->plan_name ?: 'No package'),
                    strtoupper($row->type),
                    $row->started_at?->toDateTimeString() ?? $row->created_at?->toDateTimeString(),
                    $row->expires_at?->toDateTimeString(),
                    $row->method,
                    $row->router?->name ?? $row->router_name,
                    $row->is_roaming ? ($row->roamingRouter?->name ?? $row->roaming_router_name) : '',
                    strtoupper($row->effective_status),
                    $row->last_seen_at?->toDateTimeString(),
                    $row->formatted_daily_data,
                    $row->was_connected ? 'Yes' : 'No',
                    $row->reconnection_sms_sent ? 'Yes' : 'No',
                    $row->disconnection_reason,
                    $row->fup_enabled ? 'Enabled' : 'Disabled',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ROUTER API HELPERS
    // ══════════════════════════════════════════════════════════════════════
    protected function connectRouter(Router $router): MikrotikApiService
    {
        $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
        $apiUser = $router->api_user ?: ($router->username ?: 'admin');
        $port = (int) ($router->api_port ?: 8728);
        $pass = CaptivePortalTemplates::decryptRouterPassword($router->api_password ?: $router->password);

        return new MikrotikApiService($host, $port, $apiUser, $pass, 8);
    }

    protected function resolvePackageByProfile(int $tenantId, ?string $profileName): ?PackageList
    {
        $clean = trim((string) ($profileName ?? ''));
        if ($clean === '' || strtolower($clean) === 'default') {
            return null;
        }
        $stripped = preg_replace('/^(hs_|ppp_)/', '', $clean);
        return PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($clean, $stripped) {
                $q->whereRaw('LOWER(TRIM(package)) = ?', [strtolower($clean)])
                  ->orWhereRaw('LOWER(TRIM(package)) = ?', [strtolower($stripped)]);
            })
            ->first();
    }

    protected function provisionPrepaidUserOnRouter(PrepaidUser $user, Router $router, ?PackageList $pkg, string $limitUptime): ?string
    {
        try {
            $api = $this->connectRouter($router);
            $profileName = 'default';

            if ($pkg && !empty($pkg->package)) {
                $profilePath = $user->type === 'hotspot' ? '/ip/hotspot/user/profile' : '/ppp/profile';
                $existingProf = $api->executeCommand($profilePath . '/print', [], ['name' => $pkg->package]);
                if (is_array($existingProf) && !empty($existingProf[0]['.id'])) {
                    $profileName = $pkg->package;
                } else {
                    $profData = [
                        'name'       => $pkg->package,
                        'rate-limit' => $pkg->getRateLimitString(),
                    ];
                    if ($user->type === 'hotspot') {
                        $profData['session-timeout'] = $pkg->getSessionTimeoutString();
                        $profData['shared-users'] = (string) max(1, (int) ($pkg->shared_users ?: 1));
                    }
                    $res = $api->executeCommand($profilePath . '/add', $profData);
                    if (!is_string($res) || !str_starts_with($res, 'Error:')) {
                        $profileName = $pkg->package;
                    }
                }
            }

            if ($user->type === 'hotspot') {
                $comment = 'HSmart ' . ($user->phone ?: '') . ($user->expires_at ? ' exp:' . $user->expires_at->timezone('Africa/Nairobi')->toDateTimeString() : '');
                $existing = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $user->username]);
                $payload = [
                    'name'         => $user->username,
                    'password'     => $user->password ?: $user->username,
                    'profile'      => $profileName,
                    'limit-uptime' => $limitUptime,
                    'disabled'     => 'no',
                    'comment'      => trim($comment),
                ];
                if ($user->fup_enabled && $user->fup_limit_mb > 0) {
                    $payload['limit-bytes-total'] = (string) ((int) $user->fup_limit_mb * 1048576);
                }

                if (is_array($existing) && !empty($existing[0]['.id'])) {
                    $api->executeCommand('/ip/hotspot/user/set', $payload, ['.id' => $existing[0]['.id']]);
                    try {
                        $api->executeCommand('/ip/hotspot/user/reset-counters', [], ['.id' => $existing[0]['.id']]);
                    } catch (\Throwable $rce) {}
                } else {
                    $addRes = $api->executeCommand('/ip/hotspot/user/add', $payload);
                    try {
                        if (is_array($addRes) && !empty($addRes['after']['ret'])) {
                            $api->executeCommand('/ip/hotspot/user/reset-counters', [], ['.id' => $addRes['after']['ret']]);
                        }
                    } catch (\Throwable $rce) {}
                }
            } else {
                $existing = $api->executeCommand('/ppp/secret/print', [], ['name' => $user->username]);
                $payload = [
                    'name'     => $user->username,
                    'password' => $user->password ?: '123456',
                    'service'  => $user->type === 'static' ? 'any' : 'pppoe',
                    'profile'  => $profileName,
                    'disabled' => 'no',
                    'comment'  => 'HSmart ' . ($user->phone ?: ''),
                ];
                if (is_array($existing) && !empty($existing[0]['.id'])) {
                    $api->executeCommand('/ppp/secret/set', $payload, ['.id' => $existing[0]['.id']]);
                } else {
                    $api->executeCommand('/ppp/secret/add', $payload);
                }
            }
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    protected function disableUserOnRouter(PrepaidUser $user, Router $router): ?string
    {
        try {
            $api = $this->connectRouter($router);
            $uLower = strtolower(trim($user->username));

            if ($user->type === 'hotspot') {
                // Remove active session(s)
                $actives = $api->executeCommand('/ip/hotspot/active/print');
                if (is_array($actives)) {
                    foreach ($actives as $act) {
                        if (strtolower(trim((string) ($act['user'] ?? ''))) === $uLower && !empty($act['.id'])) {
                            $api->executeCommand('/ip/hotspot/active/remove', [], ['.id' => $act['.id']]);
                        }
                    }
                }
                // Remove cookies
                $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                if (is_array($cookies)) {
                    foreach ($cookies as $ck) {
                        if (strtolower(trim((string) ($ck['user'] ?? ''))) === $uLower && !empty($ck['.id'])) {
                            $api->executeCommand('/ip/hotspot/cookie/remove', [], ['.id' => $ck['.id']]);
                        }
                    }
                }
                // Disable user
                $users = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $user->username]);
                if (is_array($users) && !empty($users[0]['.id'])) {
                    $api->executeCommand('/ip/hotspot/user/disable', [], ['.id' => $users[0]['.id']]);
                }
            } else {
                $actives = $api->executeCommand('/ppp/active/print');
                if (is_array($actives)) {
                    foreach ($actives as $act) {
                        if (strtolower(trim((string) ($act['name'] ?? ''))) === $uLower && !empty($act['.id'])) {
                            $api->executeCommand('/ppp/active/remove', [], ['.id' => $act['.id']]);
                        }
                    }
                }
                $secrets = $api->executeCommand('/ppp/secret/print', [], ['name' => $user->username]);
                if (is_array($secrets) && !empty($secrets[0]['.id'])) {
                    $api->executeCommand('/ppp/secret/disable', [], ['.id' => $secrets[0]['.id']]);
                }
            }
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    protected function removeUserFromRouter(PrepaidUser $user, Router $router): ?string
    {
        try {
            $api = $this->connectRouter($router);
            $uLower = strtolower(trim($user->username));

            if ($user->type === 'hotspot') {
                $actives = $api->executeCommand('/ip/hotspot/active/print');
                if (is_array($actives)) {
                    foreach ($actives as $act) {
                        if (strtolower(trim((string) ($act['user'] ?? ''))) === $uLower && !empty($act['.id'])) {
                            $api->executeCommand('/ip/hotspot/active/remove', [], ['.id' => $act['.id']]);
                        }
                    }
                }
                $cookies = $api->executeCommand('/ip/hotspot/cookie/print');
                if (is_array($cookies)) {
                    foreach ($cookies as $ck) {
                        if (strtolower(trim((string) ($ck['user'] ?? ''))) === $uLower && !empty($ck['.id'])) {
                            $api->executeCommand('/ip/hotspot/cookie/remove', [], ['.id' => $ck['.id']]);
                        }
                    }
                }
                $users = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $user->username]);
                if (is_array($users) && !empty($users[0]['.id'])) {
                    $api->executeCommand('/ip/hotspot/user/remove', [], ['.id' => $users[0]['.id']]);
                }
            } else {
                $actives = $api->executeCommand('/ppp/active/print');
                if (is_array($actives)) {
                    foreach ($actives as $act) {
                        if (strtolower(trim((string) ($act['name'] ?? ''))) === $uLower && !empty($act['.id'])) {
                            $api->executeCommand('/ppp/active/remove', [], ['.id' => $act['.id']]);
                        }
                    }
                }
                $secrets = $api->executeCommand('/ppp/secret/print', [], ['name' => $user->username]);
                if (is_array($secrets) && !empty($secrets[0]['.id'])) {
                    $api->executeCommand('/ppp/secret/remove', [], ['.id' => $secrets[0]['.id']]);
                }
            }
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function render()
    {
        $tenantId = $this->getTenantId();
        $users = $this->buildFilteredQuery()->paginate($this->perPage);
        $routers = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->get();
        $packages = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('price')->get();

        // Section summary counts strictly per tenant from DB (zero router calls on page load)
        $baseCount = PrepaidUser::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $counts = [
            'all'     => (clone $baseCount)->count(),
            'active'  => (clone $baseCount)->whereNotIn('status', ['expired', 'disabled'])->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'expired' => (clone $baseCount)->where(fn($q) => $q->where('status', 'expired')->orWhere(fn($s) => $s->whereNotNull('expires_at')->where('expires_at', '<=', now())))->count(),
            'online'  => (clone $baseCount)->where(fn($q) => $q->where('is_online', true)->orWhere('status', 'online'))->count(),
            'offline' => (clone $baseCount)->where('is_online', false)->whereNotIn('status', ['expired', 'disabled'])->count(),
            'roaming' => (clone $baseCount)->where('is_roaming', true)->count(),
        ];

        return view('livewire.admin.prepaid-users-hub', [
            'users'    => $users,
            'routers'  => $routers,
            'packages' => $packages,
            'counts'   => $counts,
        ])->layout('layouts.app');
    }
}

