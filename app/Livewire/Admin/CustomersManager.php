<?php

namespace App\Livewire\Admin;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\PrepaidUser;
use App\Models\Router;
use App\Scopes\TenantScope;
use App\Services\Auth\OtpVerificationService;
use App\Services\MikrotikApiService;
use App\Services\Sms\IspSmsDispatcher;
use Carbon\Carbon;
use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersManager extends Component
{
    use WithPagination;

    // Search and filters
    public string $search = '';
    public string $filterType = 'all'; // 'all', 'pppoe', 'hotspot', 'online', 'expired', 'suspended'
    public string $filterRouter = 'all';

    // New Customer Modal State
    public bool $showCreateModal = false;
    public string $new_name = '';
    public string $new_phone = '';
    public string $new_type = 'pppoe'; // 'pppoe' or 'hotspot'
    public ?int $new_router_id = null;
    public ?int $new_package_id = null;
    public string $new_username = '';
    public string $new_password = '';
    public int $new_validity_days = 30;

    // Edit Customer Modal State
    public bool $showEditModal = false;
    public ?int $editingCustomerId = null;
    public string $edit_name = '';
    public string $edit_phone = '';
    public string $edit_type = 'pppoe';
    public ?int $edit_router_id = null;
    public ?int $edit_package_id = null;
    public string $edit_username = '';
    public string $edit_password = '';
    public string $edit_status = 'active';
    public ?string $edit_expiry_date = null;

    protected $paginationTheme = 'bootstrap';

    protected function rules(): array
    {
        return [
            'new_name'          => 'required|string|min:2|max:100',
            'new_phone'         => 'required|string|min:9|max:15',
            'new_type'          => 'required|in:pppoe,hotspot',
            'new_router_id'     => 'required|exists:routers,id',
            'new_package_id'    => 'required|exists:package_lists,id',
            'new_username'      => 'required|string|min:3|max:64',
            'new_password'      => 'required|string|min:4|max:64',
            'new_validity_days' => 'required|integer|min:1|max:365',
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterType()
    {
        $this->resetPage();
    }

    public function updatingFilterRouter()
    {
        $this->resetPage();
    }

    protected function getTenantId(): int
    {
        $user = auth()->user();
        return $user?->tenant_id ?: (session('tenant_id') ?: (session('impersonated_tenant_id') ?: 1));
    }

    public function mount()
    {
        // If the tenant currently has 0 customers, automatically trigger a background sync from their online router
        $tenantId = $this->getTenantId();
        $count = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($count === 0) {
            $this->syncFromRouter(null, true);
        }
    }

    /**
     * Resolve a real tenant-scoped package from RouterOS profile name or recent hotspot sale.
     */
    protected function resolveTenantPackage(int $tenantId, ?string $profileName, ?string $phone = null, ?string $username = null): ?PackageList
    {
        $cleanProfile = trim((string) ($profileName ?? ''));
        if ($cleanProfile !== '' && strtolower($cleanProfile) !== 'default') {
            $stripped = preg_replace('/^(hs_|ppp_)/', '', $cleanProfile);
            $matched = PackageList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($cleanProfile, $stripped) {
                    $q->whereRaw('LOWER(TRIM(package)) = ?', [strtolower($cleanProfile)])
                      ->orWhereRaw('LOWER(TRIM(package)) = ?', [strtolower($stripped)]);
                })
                ->first();
            if ($matched) {
                return $matched;
            }
        }

        if ($phone || $username) {
            $sale = HotspotSale::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($phone, $username) {
                    if ($username) {
                        $q->where('voucher_code', strtoupper($username))->orWhere('username', $username);
                    }
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                })
                ->latest('id')
                ->first();

            if ($sale && !empty($sale->profile)) {
                $saleProfile = trim((string) $sale->profile);
                $stripped = preg_replace('/^(hs_|ppp_)/', '', $saleProfile);
                return PackageList::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($saleProfile, $stripped) {
                        $q->whereRaw('LOWER(TRIM(package)) = ?', [strtolower($saleProfile)])
                          ->orWhereRaw('LOWER(TRIM(package)) = ?', [strtolower($stripped)]);
                    })
                    ->first();
            }
        }

        return null;
    }

    /**
     * Decrypt router API password supporting multiple app keys and plain text.
     */
    protected function decryptRouterPassword(?string $encryptedVal): string
    {
        if (empty($encryptedVal)) {
            return '';
        }

        $keys = [
            'base64:OsvBUcJog9u3L7RHpAXOV3lAVumKIyCIOscxYwKXufQ=',
            'base64:NSLTh+nKIi546KF1WfENeIVjjBvu0SSvE2kgyiFu7CE=',
            'base64:motZaNhPAm6uv5uffwL6kbrJdtOu3qgrdgjtXarDoaU=',
            'base64:CGygtTkIhnvb+mwr8Mv5j5DjDkgmbq9ej3mmKwHcS5g=',
        ];

        try {
            return Crypt::decryptString($encryptedVal);
        } catch (\Throwable $e) {}

        foreach ($keys as $k) {
            try {
                $rawKey = base64_decode(substr($k, 7));
                $enc = new Encrypter($rawKey, 'AES-256-CBC');
                return $enc->decryptString($encryptedVal);
            } catch (\Throwable $e) {}
        }

        return $encryptedVal;
    }

    /**
     * Sync active PPPoE and Hotspot clients live from the connected MikroTik router.
     */
    public function syncFromRouter(?int $routerId = null, bool $quiet = false)
    {
        $tenantId = $this->getTenantId();

        $query = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at');

        if ($routerId) {
            $query->where('id', $routerId);
        } elseif ($this->filterRouter !== 'all' && is_numeric($this->filterRouter)) {
            $query->where('id', $this->filterRouter);
        } else {
            $query->where('status', 'online');
        }

        $routers = $query->get();
        if ($routers->isEmpty()) {
            $routers = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->get();
        }

        if ($routers->isEmpty()) {
            if (!$quiet) {
                session()->flash('warning', 'No MikroTik router found for this network. Please add a router first.');
            }
            return;
        }

        // Sort online or recently active routers first
        $sortedRouters = $routers->sortByDesc(function ($r) {
            return ($r->status === 'online' ? 100 : 0) + ($r->last_seen_at ? 50 : 0);
        });

        $totalSyncedHs = 0;
        $totalSyncedPpp = 0;
        $connectedAny = false;

        foreach ($sortedRouters as $router) {
            $host = $router->vpn_ip ?: $router->ip;
            if (empty($host)) {
                continue;
            }

            $port = (int) ($router->api_port ?: 8728);
            $user = $router->api_user ?: ($router->username ?: 'admin');
            $pass = $this->decryptRouterPassword($router->api_password ?: $router->password);

            try {
                $api = new MikrotikApiService($host, $port, $user, $pass, 4);

                // Fetch Hotspot active & users
                $hsActive = [];
                try {
                    $res = $api->executeCommand('/ip/hotspot/active/print');
                    if (is_array($res)) $hsActive = $res;
                } catch (\Throwable $e) {}

                $hsUsers = [];
                try {
                    $res = $api->executeCommand('/ip/hotspot/user/print');
                    if (is_array($res)) $hsUsers = $res;
                } catch (\Throwable $e) {}

                // Fetch PPPoE active & secrets
                $pppActive = [];
                try {
                    $res = $api->executeCommand('/ppp/active/print');
                    if (is_array($res)) $pppActive = $res;
                } catch (\Throwable $e) {}

                $pppSecrets = [];
                try {
                    $res = $api->executeCommand('/ppp/secret/print');
                    if (is_array($res)) $pppSecrets = $res;
                } catch (\Throwable $e) {}

                $connectedAny = true;

                // Build lookup maps
                $hsActiveMap = [];
                foreach ($hsActive as $h) {
                    if (!empty($h['user'])) {
                        $hsActiveMap[$h['user']] = $h;
                    }
                }

                $pppActiveMap = [];
                foreach ($pppActive as $p) {
                    if (!empty($p['name'])) {
                        $pppActiveMap[$p['name']] = $p;
                    }
                }

                // Combine Hotspot users
                $allHs = $hsUsers;
                foreach ($hsActiveMap as $uName => $aData) {
                    $found = false;
                    foreach ($allHs as $hu) {
                        if (($hu['name'] ?? '') === $uName) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $allHs[] = [
                            'name'        => $uName,
                            'comment'     => $aData['comment'] ?? '',
                            'address'     => $aData['address'] ?? null,
                            'mac-address' => $aData['mac-address'] ?? null,
                        ];
                    }
                }

                // Process Hotspot clients
                foreach ($allHs as $hu) {
                    $uName = trim($hu['name'] ?? '');
                    if (empty($uName) || $uName === 'default-trial') {
                        continue;
                    }

                    $comment = $hu['comment'] ?? '';
                    $activeData = $hsActiveMap[$uName] ?? null;
                    $ip = $activeData['address'] ?? ($hu['address'] ?? null);
                    $mac = $activeData['mac-address'] ?? ($hu['mac-address'] ?? null);

                    $phone = '';
                    if (preg_match('/(?:254|0)[17]\d{8}/', $comment, $m)) {
                        $phone = $m[0];
                    }

                    $customerName = !empty($phone) ? "Customer ({$phone})" : "Hotspot ({$uName})";
                    if (!empty($comment) && !str_starts_with($comment, 'M-Pesa')) {
                        $customerName = $comment;
                    }

                    $matchedPkg = $this->resolveTenantPackage($tenantId, $hu['profile'] ?? null, $phone ?: null, $uName);

                    $secret = PPPSecrets::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'username'  => $uName,
                        ],
                        [
                            'router_id'     => $router->id,
                            'router_name'   => $router->name,
                            'password'      => $hu['password'] ?? '******',
                            'service'       => 'hotspot',
                            'profile'       => $matchedPkg?->package ?: ($hu['profile'] ?? 'default'),
                            'status'        => ($hu['disabled'] ?? 'false') === 'true' ? 'disabled' : 'active',
                            'caller_id'     => $mac,
                            'ppp_remote_ip' => $ip,
                        ]
                    );

                    $uniqueId = 'HS-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $uName), 0, 12));
                    $cust = CustomersInfo::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where(function ($q) use ($uniqueId, $secret) {
                            $q->where('customer_unique_id', $uniqueId)
                              ->orWhere('ppp_user_id', $secret->id);
                        })
                        ->first();

                    if (!$cust) {
                        $cust = CustomersInfo::withoutGlobalScopes()->create([
                            'tenant_id'          => $tenantId,
                            'customer_unique_id' => $uniqueId,
                            'customer_name'      => $customerName,
                            'mobile'             => $phone ?: '254700000000',
                            'service_type'       => 'hotspot',
                            'router_id'          => $router->id,
                            'package_id'         => $matchedPkg?->id,
                            'ppp_user_id'        => $secret->id,
                            'connection_date'    => now(),
                            'status'             => 'active',
                        ]);
                    } else {
                        $cust->update([
                            'customer_name' => $customerName,
                            'mobile'        => $phone ?: ($cust->mobile ?: '254700000000'),
                            'service_type'  => 'hotspot',
                            'router_id'     => $router->id,
                            'package_id'    => $matchedPkg?->id ?? $cust->package_id,
                            'ppp_user_id'   => $secret->id,
                            'status'        => 'active',
                        ]);
                    }

                    $pkgPrice = (float) ($matchedPkg?->price ?? 0);
                    BillingInfo::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id'               => $tenantId,
                            'customer_bill_unique_id' => $cust->customer_unique_id,
                        ],
                        [
                            'billing_type'      => 'prepaid',
                            'monthly_rent'      => $pkgPrice,
                            'total_amount'      => $pkgPrice,
                            'paid_amount'       => $pkgPrice,
                            'due_amount'        => 0,
                            'auto_disable'      => true,
                            'auto_disable_date' => now()->addDay(),
                        ]
                    );

                    PrepaidUser::upsertFromCustomer($cust, $secret, $matchedPkg, now()->addDay());

                    $totalSyncedHs++;
                }

                // Combine PPPoE users
                $allPpp = $pppSecrets;
                foreach ($pppActiveMap as $uName => $aData) {
                    $found = false;
                    foreach ($allPpp as $pu) {
                        if (($pu['name'] ?? '') === $uName) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $allPpp[] = [
                            'name'           => $uName,
                            'service'        => 'pppoe',
                            'profile'        => 'default',
                            'comment'        => $aData['comment'] ?? '',
                            'remote-address' => $aData['address'] ?? null,
                            'caller-id'      => $aData['caller-id'] ?? null,
                        ];
                    }
                }

                // Process PPPoE clients
                foreach ($allPpp as $pu) {
                    $uName = trim($pu['name'] ?? '');
                    if (empty($uName)) {
                        continue;
                    }

                    $activeData = $pppActiveMap[$uName] ?? null;
                    $ip = $activeData['address'] ?? ($pu['remote-address'] ?? null);
                    $mac = $activeData['caller-id'] ?? ($pu['caller-id'] ?? null);
                    $comment = $pu['comment'] ?? '';

                    $phone = '';
                    if (preg_match('/(?:254|0)[17]\d{8}/', $comment, $m)) {
                        $phone = $m[0];
                    }

                    $customerName = !empty($phone) ? "Customer ({$phone})" : "PPPoE ({$uName})";
                    if (!empty($comment)) {
                        $customerName = $comment;
                    }

                    $matchedPppPkg = $this->resolveTenantPackage($tenantId, $pu['profile'] ?? null, $phone ?: null, $uName);

                    $secret = PPPSecrets::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'username'  => $uName,
                        ],
                        [
                            'router_id'     => $router->id,
                            'router_name'   => $router->name,
                            'password'      => $pu['password'] ?? '******',
                            'service'       => 'pppoe',
                            'profile'       => $matchedPppPkg?->package ?: ($pu['profile'] ?? 'default'),
                            'status'        => ($pu['disabled'] ?? 'false') === 'true' ? 'disabled' : 'active',
                            'caller_id'     => $mac,
                            'ppp_remote_ip' => $ip,
                        ]
                    );

                    $uniqueId = 'PPP-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $uName), 0, 12));
                    $cust = CustomersInfo::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where(function ($q) use ($uniqueId, $secret) {
                            $q->where('customer_unique_id', $uniqueId)
                              ->orWhere('ppp_user_id', $secret->id);
                        })
                        ->first();

                    if (!$cust) {
                        $cust = CustomersInfo::withoutGlobalScopes()->create([
                            'tenant_id'          => $tenantId,
                            'customer_unique_id' => $uniqueId,
                            'customer_name'      => $customerName,
                            'mobile'             => $phone ?: '254700000000',
                            'service_type'       => 'pppoe',
                            'router_id'          => $router->id,
                            'package_id'         => $matchedPppPkg?->id,
                            'ppp_user_id'        => $secret->id,
                            'connection_date'    => now(),
                            'status'             => ($pu['disabled'] ?? 'false') === 'true' ? 'suspended' : 'active',
                        ]);
                    } else {
                        $cust->update([
                            'customer_name' => $customerName,
                            'mobile'        => $phone ?: ($cust->mobile ?: '254700000000'),
                            'service_type'  => 'pppoe',
                            'router_id'     => $router->id,
                            'package_id'    => $matchedPppPkg?->id ?? $cust->package_id,
                            'ppp_user_id'   => $secret->id,
                            'status'        => ($pu['disabled'] ?? 'false') === 'true' ? 'suspended' : 'active',
                        ]);
                    }

                    $pppPrice = (float) ($matchedPppPkg?->price ?? 0);
                    BillingInfo::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id'               => $tenantId,
                            'customer_bill_unique_id' => $cust->customer_unique_id,
                        ],
                        [
                            'billing_type'      => 'prepaid',
                            'monthly_rent'      => $pppPrice,
                            'total_amount'      => $pppPrice,
                            'paid_amount'       => $pppPrice,
                            'due_amount'        => 0,
                            'auto_disable'      => true,
                            'auto_disable_date' => now()->addDays(30),
                        ]
                    );

                    PrepaidUser::upsertFromCustomer($cust, $secret, $matchedPppPkg, now()->addDays(30));

                    $totalSyncedPpp++;
                }

                // Update router telemetry snapshot
                $router->updateQuietly([
                    'status'       => 'online',
                    'last_seen_at' => now(),
                ]);

            } catch (\Throwable $e) {
                Log::warning("[CustomersManager] Could not sync router {$router->name}: " . $e->getMessage());
            }
        }

        if (!$quiet) {
            if ($connectedAny) {
                session()->flash('success', "Synced successfully: {$totalSyncedHs} Hotspot and {$totalSyncedPpp} PPPoE clients from MikroTik.");
            } else {
                session()->flash('warning', 'Could not establish connection to the MikroTik router. Please verify WireGuard VPN is active.');
            }
        }
    }

    /**
     * Send instant SMS payment reminder with balance and payment details.
     */
    public function sendPaymentReminder(int $customerId)
    {
        $customer = CustomersInfo::where('id', $customerId)->first();
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            return;
        }

        $phone = $customer->mobile;
        if (empty($phone)) {
            session()->flash('error', "Customer {$customer->customer_name} has no phone number on record.");
            return;
        }

        $tenantId = $this->getTenantId();
        $billing = BillingInfo::where('customer_bill_unique_id', $customer->customer_unique_id)->first();
        $amountDue = $billing ? (float) ($billing->total_due_amount ?: ($billing->due_amount ?: $billing->monthly_rent)) : 0;
        if ($amountDue <= 0 && $customer->package) {
            $amountDue = (float) ($customer->package->price ?? 1000);
        }

        // Fetch ISP payment gateway / paybill if set
        $gateway = DB::table('isp_payment_gateways')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        $accountStr = $gateway 
            ? "Paybill {$gateway->shortcode}, Acc: {$customer->customer_unique_id}" 
            : "Acc: {$customer->customer_unique_id}";

        $message = "Dear {$customer->customer_name}, your internet subscription payment of KES " . number_format($amountDue, 2) . " is due. Please pay via {$accountStr} to ensure uninterrupted service. Thank you for choosing us!";

        $sent = IspSmsDispatcher::sendDirectSms($phone, $message, $tenantId);

        if ($sent) {
            session()->flash('success', "Payment reminder successfully dispatched to {$customer->customer_name} ({$phone}).");
        } else {
            session()->flash('warning', "SMS gateway was queued for {$phone}.");
        }
    }

    /**
     * Suspend or unblock customer service on database and MikroTik.
     */
    public function toggleSuspend(int $customerId)
    {
        $customer = CustomersInfo::where('id', $customerId)->first();
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            return;
        }

        $newStatus = ($customer->status === 'disable' || $customer->status === 'suspended') ? 'active' : 'suspended';
        $customer->update(['status' => $newStatus]);

        // If customer has a PPPoE secret, update on router
        if ($customer->pppUser) {
            $secret = $customer->pppUser;
            $secret->update(['status' => $newStatus === 'active' ? 'active' : 'disabled']);

            $router = Router::where('name', $secret->router_name)->first();
            if ($router && $router->status === 'online') {
                try {
                    $host = $router->vpn_ip ?: $router->ip;
                    $port = (int) ($router->api_port ?: 8728);
                    $user = $router->api_user ?: ($router->username ?: 'admin');
                    $pass = '';
                    try {
                        $pass = Crypt::decryptString($router->api_password ?: $router->password);
                    } catch (\Throwable $e) {
                        $pass = $router->api_password ?: $router->password;
                    }

                    $api = new MikrotikApiService($host, $port, $user, $pass, 4);

                    // Set disabled flag on secret
                    $api->executeCommand('/ppp/secret/set', [
                        'disabled' => $newStatus === 'active' ? 'no' : 'yes',
                    ], ['name' => $secret->username]);

                    // If suspending, kick active session immediately
                    if ($newStatus === 'suspended') {
                        $api->executeCommand('/ppp/active/remove', [], ['name' => $secret->username]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("[CustomersManager] Could not update MikroTik secret for {$secret->username}: " . $e->getMessage());
                }
            }
        }

        session()->flash('success', "Customer {$customer->customer_name} is now " . ucfirst($newStatus) . ".");
    }

    /**
     * Disconnect / kick active session on MikroTik.
     */
    public function kickSession(int $customerId)
    {
        $customer = CustomersInfo::where('id', $customerId)->first();
        if (!$customer || !$customer->pppUser) {
            session()->flash('error', 'Customer PPPoE session not found.');
            return;
        }

        $secret = $customer->pppUser;
        $router = Router::where('name', $secret->router_name)->first();

        if (!$router || $router->status !== 'online') {
            session()->flash('warning', "Router {$secret->router_name} is offline. Session cannot be dropped right now.");
            return;
        }

        try {
            $host = $router->vpn_ip ?: $router->ip;
            $port = (int) ($router->api_port ?: 8728);
            $user = $router->api_user ?: ($router->username ?: 'admin');
            $pass = '';
            try {
                $pass = Crypt::decryptString($router->api_password ?: $router->password);
            } catch (\Throwable $e) {
                $pass = $router->api_password ?: $router->password;
            }

            $api = new MikrotikApiService($host, $port, $user, $pass, 4);
            $api->executeCommand('/ppp/active/remove', [], ['name' => $secret->username]);

            session()->flash('success', "Active session for {$secret->username} disconnected. Router will force re-authentication.");
        } catch (\Throwable $e) {
            session()->flash('error', "Failed to disconnect session: " . $e->getMessage());
        }
    }

    /**
     * Open Create Customer Modal
     */
    public function openCreateModal()
    {
        $this->reset([
            'new_name', 'new_phone', 'new_type', 'new_router_id', 'new_package_id',
            'new_username', 'new_password', 'new_validity_days'
        ]);

        $this->new_type = 'pppoe';
        $this->new_validity_days = 30;
        $this->new_password = Str::random(8);

        $tenantId = $this->getTenantId();
        $firstRouter = Router::where('tenant_id', $tenantId)->first();
        if ($firstRouter) {
            $this->new_router_id = $firstRouter->id;
        }

        $this->autoSelectPackageForType('pppoe');
        $this->showCreateModal = true;
    }

    public function updatedNewType($value): void
    {
        $this->autoSelectPackageForType($value);
    }

    public function updatedEditType($value): void
    {
        $this->autoSelectPackageForEdit($value);
    }

    /**
     * Auto-select matching package for service type (PPPoE vs Hotspot) strictly for this tenant.
     */
    public function autoSelectPackageForType(string $type): void
    {
        $tenantId = $this->getTenantId();

        $pkg = PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type === 'hotspot' ? 'hotspot' : 'pppoe')
            ->first();

        $this->new_package_id = $pkg?->id;
    }

    public function autoSelectPackageForEdit(string $type): void
    {
        $tenantId = $this->getTenantId();

        $pkg = PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type === 'hotspot' ? 'hotspot' : 'pppoe')
            ->first();

        if ($pkg) {
            $this->edit_package_id = $pkg->id;
        }
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    /**
     * Store New Customer
     */
    public function storeCustomer()
    {
        $this->validate();

        $tenantId = $this->getTenantId();
        $normalizedPhone = OtpVerificationService::normalizeKenyanPhone($this->new_phone) ?: $this->new_phone;
        $uniqueId = 'CUST-' . strtoupper(Str::random(6));

        $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('id', $this->new_router_id)->first();
        $package = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $this->new_package_id)->first();

        // 0. Ensure router is in legacy router_lists as safety fallback
        if ($router) {
            try {
                $rName = $router->name ?: ($router->router_name ?: 'Router-' . $router->id);
                DB::table('router_lists')->updateOrInsert(
                    ['router_name' => $rName],
                    [
                        'tenant_id'  => $tenantId,
                        'ip_address' => $router->vpn_ip ?: ($router->ip_address ?: ($router->ip ?: '10.10.0.1')),
                        'username'   => $router->api_user ?: ($router->username ?: 'admin'),
                        'password'   => $router->api_password ?: ($router->password ?: ''),
                        'api_port'   => $router->api_port ?: 8728,
                        'action'     => 'connected',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {}
        }

        try {
            DB::beginTransaction();

            // 1. Create PPP Secret
            $secret = PPPSecrets::create([
                'tenant_id'   => $tenantId,
                'router_id'   => $router?->id,
                'router_name' => $router?->name ?: 'Main Gateway',
                'username'    => strtolower(trim($this->new_username)),
                'password'    => $this->new_password,
                'service'     => $this->new_type,
                'profile'     => $package?->name ?: 'default',
                'status'      => 'active',
            ]);

            // 2. Create Customer Record
            $customer = CustomersInfo::create([
                'tenant_id'          => $tenantId,
                'customer_unique_id' => $uniqueId,
                'customer_name'      => $this->new_name,
                'mobile'             => $normalizedPhone,
                'service_type'       => $this->new_type,
                'router_id'          => $this->new_router_id,
                'package_id'         => $this->new_package_id,
                'ppp_user_id'        => $secret->id,
                'connection_date'    => now(),
                'status'             => 'active',
            ]);

            // 3. Create Billing Info
            $rent = (float) ($package?->price ?? 0);
            BillingInfo::create([
                'tenant_id'               => $tenantId,
                'customer_bill_unique_id' => $uniqueId,
                'billing_type'            => 'prepaid',
                'monthly_rent'            => $rent,
                'total_amount'            => $rent,
                'due_amount'              => 0,
                'previous_due'            => 0,
                'paid_amount'             => $rent,
                'paid_date'               => now(),
                'auto_disable'            => true,
                'auto_disable_date'       => now()->addDays($this->new_validity_days),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[CustomersManager] Failed to store customer: " . $e->getMessage());
            session()->flash('error', 'Failed to create customer: ' . $e->getMessage());
            return;
        }

        // 4. Provision to MikroTik router if online
        if ($router && $router->status === 'online') {
            try {
                $host = $router->vpn_ip ?: $router->ip;
                $port = (int) ($router->api_port ?: 8728);
                $user = $router->api_user ?: ($router->username ?: 'admin');
                $pass = '';
                try {
                    $pass = Crypt::decryptString($router->api_password ?: $router->password);
                } catch (\Throwable $e) {
                    $pass = $router->api_password ?: $router->password;
                }

                $api = new MikrotikApiService($host, $port, $user, $pass, 4);

                if ($this->new_type === 'hotspot') {
                    $existing = $api->executeCommand('/ip/hotspot/user/print', [], ['name' => $secret->username]);
                    if (empty($existing) || !is_array($existing)) {
                        $api->executeCommand('/ip/hotspot/user/add', [
                            'name'     => $secret->username,
                            'password' => $secret->password,
                            'profile'  => $package?->name ?: 'default',
                            'comment'  => "HSmart {$uniqueId} ({$this->new_name})",
                        ]);
                    }
                } else {
                    $existing = $api->executeCommand('/ppp/secret/print', [], ['name' => $secret->username]);
                    if (empty($existing) || !is_array($existing)) {
                        $api->executeCommand('/ppp/secret/add', [
                            'name'     => $secret->username,
                            'password' => $secret->password,
                            'service'  => 'pppoe',
                            'profile'  => $package?->name ?: 'default',
                            'comment'  => "HSmart {$uniqueId} ({$this->new_name})",
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("[CustomersManager] Could not sync user to MikroTik: " . $e->getMessage());
            }
        }

        PrepaidUser::upsertFromCustomer(
            $customer,
            $secret,
            $package,
            now()->addDays($this->new_validity_days)
        );

        // 5. Send confirmation SMS with login credentials
        try {
            $welcomeMsg = "Dear {$this->new_name}, welcome to HSmart WiFi! Your " . strtoupper($this->new_type) . " account is ready. Username: {$secret->username}, Password: {$secret->password}. Package: " . ($package?->name ?? 'No package') . ". Enjoy high-speed internet!";
            IspSmsDispatcher::sendDirectSms($customer->mobile, $welcomeMsg, $tenantId);
        } catch (\Throwable $se) {
            Log::warning("[CustomersManager] Welcome SMS notification error: " . $se->getMessage());
        }

        session()->flash('success', "Customer {$this->new_name} ({$uniqueId}) created successfully.");
        $this->closeCreateModal();
    }

    /**
     * Open Edit Customer Modal
     */
    public function openEditModal(int $customerId)
    {
        $tenantId = $this->getTenantId();
        $customer = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('id', $customerId)
            ->first();
        if (!$customer) {
            return;
        }

        $this->editingCustomerId = $customer->id;
        $this->edit_name = $customer->customer_name ?: '';
        $this->edit_phone = $customer->mobile ?: '';
        $this->edit_type = $customer->service_type ?: 'pppoe';
        $this->edit_router_id = $customer->router_id;
        $this->edit_package_id = $customer->package_id;
        $this->edit_status = $customer->status ?: 'active';

        $secret = $customer->pppUser;
        $this->edit_username = $secret?->username ?: '';
        $this->edit_password = $secret?->password ?: '';

        $billing = BillingInfo::where('customer_bill_unique_id', $customer->customer_unique_id)->first();
        $this->edit_expiry_date = $billing?->auto_disable_date ? Carbon::parse($billing->auto_disable_date)->format('Y-m-d') : null;

        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingCustomerId = null;
        $this->resetValidation();
    }

    /**
     * Update Customer
     */
    public function updateCustomer()
    {
        $tenantId = $this->getTenantId();
        $customer = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('id', $this->editingCustomerId)
            ->first();
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            $this->closeEditModal();
            return;
        }

        $package = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $this->edit_package_id)->first();
        $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('id', $this->edit_router_id)->first();

        $customer->update([
            'customer_name' => $this->edit_name,
            'mobile'        => OtpVerificationService::normalizeKenyanPhone($this->edit_phone) ?: $this->edit_phone,
            'service_type'  => $this->edit_type,
            'router_id'     => $this->edit_router_id,
            'package_id'    => $package?->id,
            'status'        => $this->edit_status,
        ]);

        if ($customer->pppUser) {
            $customer->pppUser->update([
                'router_name' => $router?->name ?: $customer->pppUser->router_name,
                'username'    => $this->edit_username,
                'password'    => $this->edit_password,
                'profile'     => $package?->name ?: 'default',
                'status'      => $this->edit_status === 'suspended' ? 'disabled' : 'active',
            ]);
        }

        if ($this->edit_expiry_date) {
            BillingInfo::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('customer_bill_unique_id', $customer->customer_unique_id)
                ->update([
                    'auto_disable_date' => Carbon::parse($this->edit_expiry_date)->endOfDay(),
                    'monthly_rent'      => (float) ($package?->price ?? 0),
                ]);
        }

        PrepaidUser::upsertFromCustomer(
            $customer->fresh(),
            $customer->pppUser,
            $package,
            $this->edit_expiry_date ? Carbon::parse($this->edit_expiry_date)->endOfDay() : null
        );

        session()->flash('success', "Customer {$this->edit_name} updated successfully.");
        $this->closeEditModal();
    }

    /**
     * Delete Customer
     */
    public function deleteCustomer(int $customerId)
    {
        $tenantId = $this->getTenantId();
        $customer = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('id', $customerId)
            ->first();
        if (!$customer) {
            return;
        }

        $name = $customer->customer_name;
        PrepaidUser::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->delete();
        if ($customer->pppUser) {
            $customer->pppUser->delete();
        }
        $customer->delete();

        session()->flash('success', "Customer {$name} deleted successfully.");
    }

    public function render()
    {
        $tenantId = $this->getTenantId();

        // Base Query with live tenant-scoped package relationship
        $query = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->with([
                'pppUser',
                'package' => fn($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId),
            ])
            ->where('customers_infos.tenant_id', $tenantId);

        // Search Filter
        if (!empty($this->search)) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', $term)
                  ->orWhere('mobile', 'like', $term)
                  ->orWhere('customer_unique_id', 'like', $term)
                  ->orWhereHas('pppUser', function ($sub) use ($term) {
                      $sub->where('username', 'like', $term)
                          ->orWhere('caller_id', 'like', $term)
                          ->orWhere('ppp_remote_ip', 'like', $term);
                  });
            });
        }

        // Router Filter
        if ($this->filterRouter !== 'all' && is_numeric($this->filterRouter)) {
            $query->where('router_id', $this->filterRouter);
        }

        // Type Filter
        if ($this->filterType === 'pppoe') {
            $query->where(function ($q) {
                $q->where('service_type', 'pppoe')
                  ->orWhereHas('pppUser', fn($s) => $s->where('service', 'pppoe'))
                  ->orWhereNull('service_type');
            });
        } elseif ($this->filterType === 'hotspot') {
            $query->where(function ($q) {
                $q->where('service_type', 'hotspot')
                  ->orWhereHas('pppUser', fn($s) => $s->where('service', 'hotspot'));
            });
        } elseif ($this->filterType === 'suspended') {
            $query->whereIn('status', ['disable', 'suspended', 'inactive']);
        } elseif ($this->filterType === 'active') {
            $query->where('status', 'active');
        } elseif ($this->filterType === 'online') {
            $query->where('status', 'active');
        }

        $customers = $query->latest('customers_infos.id')->paginate(15);

        // Fetch Bill info for current page
        $uniqueIds = $customers->pluck('customer_unique_id')->toArray();
        $billings = BillingInfo::withoutGlobalScope(TenantScope::class)->whereIn('customer_bill_unique_id', $uniqueIds)->get()->keyBy('customer_bill_unique_id');

        // Analytics Calculation (Tenant Scoped)
        $allTenantCustomers = CustomersInfo::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->get();

        $totalCustomers = $allTenantCustomers->count();
        $activeCustomers = $allTenantCustomers->where('status', 'active')->count();
        $suspendedCustomers = $allTenantCustomers->whereIn('status', ['disable', 'suspended'])->count();

        // Calculate Revenue and Dues from packages & billing
        $allUniqueIds = $allTenantCustomers->pluck('customer_unique_id')->toArray();
        $allBillings = BillingInfo::withoutGlobalScope(TenantScope::class)->whereIn('customer_bill_unique_id', $allUniqueIds)->get();

        $monthlyRevenue = (float) $allBillings->sum('monthly_rent');
        if ($monthlyRevenue <= 0) {
            $packageMap = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('price', 'id')->toArray();
            foreach ($allTenantCustomers as $c) {
                if ($c->package_id && isset($packageMap[$c->package_id])) {
                    $monthlyRevenue += (float) $packageMap[$c->package_id];
                }
            }
        }

        $outstandingBalance = (float) $allBillings->sum('due_amount');

        // Routers and Packages for Modals and Filters
        $routers = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->get();

        // 1. Packages strictly matching this tenant & service type
        $createPackages = PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $this->new_type === 'hotspot' ? 'hotspot' : 'pppoe')
            ->get();

        if ($createPackages->isEmpty()) {
            $createPackages = PackageList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->get();
        }

        // 2. Packages strictly matching this tenant & edit service type
        $editPackages = PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $this->edit_type === 'hotspot' ? 'hotspot' : 'pppoe')
            ->get();

        if ($editPackages->isEmpty()) {
            $editPackages = $createPackages;
        }

        return view('livewire.admin.customers-manager', [
            'customers'          => $customers,
            'billings'           => $billings,
            'totalCustomers'     => $totalCustomers,
            'activeCustomers'    => $activeCustomers,
            'suspendedCustomers' => $suspendedCustomers,
            'monthlyRevenue'     => $monthlyRevenue,
            'outstandingBalance' => $outstandingBalance,
            'routers'            => $routers,
            'packages'           => $createPackages,
            'createPackages'     => $createPackages,
            'editPackages'       => $editPackages,
            'filterType'         => $this->filterType,
            'filterRouter'       => $this->filterRouter,
            'search'             => $this->search,
            'showCreateModal'    => $this->showCreateModal,
            'showEditModal'      => $this->showEditModal,
        ])->layout('layouts.app');
    }
}

