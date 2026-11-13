<?php

namespace App\Livewire\Admin;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\Router;
use App\Services\Auth\OtpVerificationService;
use App\Services\MikrotikApiService;
use App\Services\Sms\IspSmsDispatcher;
use Carbon\Carbon;
use Exception;
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
     * Auto-select or create matching package for service type (PPPoE vs Hotspot).
     */
    public function autoSelectPackageForType(string $type): void
    {
        $tenantId = $this->getTenantId();

        $pkg = PackageList::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where(function ($q) use ($type) {
                if ($type === 'hotspot') {
                    $q->where('type', 'hotspot')->orWhere('package', 'like', '%hotspot%');
                } else {
                    $q->where('type', 'pppoe')
                      ->orWhere('package', 'like', '%pppoe%')
                      ->orWhere('package', 'like', '%fiber%')
                      ->orWhereNull('type');
                }
            })
            ->first();

        if (! $pkg) {
            // Auto-provision a starter package for this tenant so the dropdown is never empty
            if ($type === 'pppoe') {
                $pkg = PackageList::create([
                    'tenant_id'           => $tenantId,
                    'package'             => '10 Mbps Home Fiber (30 Days)',
                    'type'                => 'pppoe',
                    'price'               => 1500,
                    'speed'               => '10M/10M',
                    'mikrotik_rate_limit' => '10M/10M',
                    'description'         => 'Fast residential fiber unlimited access',
                    'duration_value'      => 30,
                    'duration_unit'       => 'days',
                    'is_active'           => true,
                    'show_on_site'        => true,
                ]);
            } else {
                $pkg = PackageList::create([
                    'tenant_id'           => $tenantId,
                    'package'             => '24 Hours Unlimited Hotspot',
                    'type'                => 'hotspot',
                    'price'               => 50,
                    'speed'               => '5M/5M',
                    'mikrotik_rate_limit' => '5M/5M',
                    'description'         => 'Daily unlimited high-speed hotspot access',
                    'duration_value'      => 24,
                    'duration_unit'       => 'hours',
                    'is_active'           => true,
                    'show_on_site'        => true,
                ]);
            }
        }

        if ($pkg) {
            $this->new_package_id = $pkg->id;
        }
    }

    public function autoSelectPackageForEdit(string $type): void
    {
        $tenantId = $this->getTenantId();

        $pkg = PackageList::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where(function ($q) use ($type) {
                if ($type === 'hotspot') {
                    $q->where('type', 'hotspot')->orWhere('package', 'like', '%hotspot%');
                } else {
                    $q->where('type', 'pppoe')
                      ->orWhere('package', 'like', '%pppoe%')
                      ->orWhere('package', 'like', '%fiber%')
                      ->orWhereNull('type');
                }
            })
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

        $router = Router::where('id', $this->new_router_id)->first();
        $package = PackageList::withoutGlobalScopes()->where('id', $this->new_package_id)->first();

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

        // 5. Send confirmation SMS with login credentials
        try {
            $welcomeMsg = "Dear {$this->new_name}, welcome to HSmart WiFi! Your " . strtoupper($this->new_type) . " account is ready. Username: {$secret->username}, Password: {$secret->password}. Package: " . ($package?->name ?? 'Standard') . ". Enjoy high-speed internet!";
            IspSmsDispatcher::sendDirectSms($customer->mobile, $welcomeMsg, null);
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
        $customer = CustomersInfo::where('id', $customerId)->first();
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
        $customer = CustomersInfo::where('id', $this->editingCustomerId)->first();
        if (!$customer) {
            session()->flash('error', 'Customer not found.');
            $this->closeEditModal();
            return;
        }

        $package = PackageList::where('id', $this->edit_package_id)->first();
        $router = Router::where('id', $this->edit_router_id)->first();

        $customer->update([
            'customer_name' => $this->edit_name,
            'mobile'        => OtpVerificationService::normalizeKenyanPhone($this->edit_phone) ?: $this->edit_phone,
            'service_type'  => $this->edit_type,
            'router_id'     => $this->edit_router_id,
            'package_id'    => $this->edit_package_id,
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
            BillingInfo::where('customer_bill_unique_id', $customer->customer_unique_id)->update([
                'auto_disable_date' => Carbon::parse($this->edit_expiry_date)->endOfDay(),
            ]);
        }

        session()->flash('success', "Customer {$this->edit_name} updated successfully.");
        $this->closeEditModal();
    }

    /**
     * Delete Customer
     */
    public function deleteCustomer(int $customerId)
    {
        $customer = CustomersInfo::where('id', $customerId)->first();
        if (!$customer) {
            return;
        }

        $name = $customer->customer_name;
        if ($customer->pppUser) {
            $customer->pppUser->delete();
        }
        $customer->delete();

        session()->flash('success', "Customer {$name} deleted successfully.");
    }

    public function render()
    {
        $tenantId = $this->getTenantId();

        // Base Query with eager loading
        $query = CustomersInfo::query()
            ->with(['pppUser', 'package'])
            ->where(function ($q) use ($tenantId) {
                $q->where('customers_infos.tenant_id', $tenantId)
                  ->orWhereNull('customers_infos.tenant_id');
            });

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
                $q->where('service_type', 'pppoe')->orWhereNull('service_type');
            });
        } elseif ($this->filterType === 'hotspot') {
            $query->where('service_type', 'hotspot');
        } elseif ($this->filterType === 'suspended') {
            $query->whereIn('status', ['disable', 'suspended', 'inactive']);
        } elseif ($this->filterType === 'active') {
            $query->where('status', 'active');
        }

        $customers = $query->latest('customers_infos.id')->paginate(15);

        // Fetch Bill info for current page
        $uniqueIds = $customers->pluck('customer_unique_id')->toArray();
        $billings = BillingInfo::whereIn('customer_bill_unique_id', $uniqueIds)->get()->keyBy('customer_bill_unique_id');

        // Analytics Calculation (Tenant Scoped)
        $allTenantCustomers = CustomersInfo::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        })->get();

        $totalCustomers = $allTenantCustomers->count();
        $activeCustomers = $allTenantCustomers->where('status', 'active')->count();
        $suspendedCustomers = $allTenantCustomers->whereIn('status', ['disable', 'suspended'])->count();

        // Calculate Revenue and Dues from packages & billing
        $allUniqueIds = $allTenantCustomers->pluck('customer_unique_id')->toArray();
        $allBillings = BillingInfo::whereIn('customer_bill_unique_id', $allUniqueIds)->get();

        $monthlyRevenue = (float) $allBillings->sum('monthly_rent');
        if ($monthlyRevenue <= 0) {
            $packageMap = PackageList::where('tenant_id', $tenantId)->pluck('price', 'id')->toArray();
            foreach ($allTenantCustomers as $c) {
                if ($c->package_id && isset($packageMap[$c->package_id])) {
                    $monthlyRevenue += (float) $packageMap[$c->package_id];
                }
            }
        }

        $outstandingBalance = (float) $allBillings->sum('due_amount');

        // Routers and Packages for Modals and Filters (Scoped to tenant or global templates)
        $routers = Router::where('tenant_id', $tenantId)->get();

        // 1. Packages specifically matching the Create modal's current service type (PPPoE vs Hotspot)
        $createPackages = PackageList::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where(function ($q) {
                if ($this->new_type === 'hotspot') {
                    $q->where('type', 'hotspot')->orWhere('package', 'like', '%hotspot%');
                } else {
                    $q->where('type', 'pppoe')
                      ->orWhere('package', 'like', '%pppoe%')
                      ->orWhere('package', 'like', '%fiber%')
                      ->orWhereNull('type');
                }
            })
            ->get();

        if ($createPackages->isEmpty()) {
            $createPackages = PackageList::withoutGlobalScopes()
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->get();
        }

        // 2. Packages specifically matching the Edit modal's service type
        $editPackages = PackageList::withoutGlobalScopes()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where(function ($q) {
                if ($this->edit_type === 'hotspot') {
                    $q->where('type', 'hotspot')->orWhere('package', 'like', '%hotspot%');
                } else {
                    $q->where('type', 'pppoe')
                      ->orWhere('package', 'like', '%pppoe%')
                      ->orWhere('package', 'like', '%fiber%')
                      ->orWhereNull('type');
                }
            })
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

