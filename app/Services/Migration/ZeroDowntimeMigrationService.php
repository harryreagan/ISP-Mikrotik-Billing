<?php

namespace App\Services\Migration;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\HotspotVoucher;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZeroDowntimeMigrationService
{
    /**
     * Inspect a live MikroTik router and return a discovery summary of accounts and live connections.
     * Zero impact on active users.
     */
    public function scanRouter(string $host, int $port, string $user, string $password): array
    {
        $api = new MikrotikApiService($host, $port, $user, $password, 5);

        // Fetch PPPoE secrets & active
        $pppSecrets = $api->executeCommand('/ppp/secret/print') ?: [];
        $pppProfiles = $api->executeCommand('/ppp/profile/print') ?: [];
        $pppActive = $api->executeCommand('/ppp/active/print') ?: [];

        // Fetch Hotspot users & active
        $hsUsers = $api->executeCommand('/ip/hotspot/user/print') ?: [];
        $hsProfiles = $api->executeCommand('/ip/hotspot/user/profile/print') ?: [];
        $hsActive = $api->executeCommand('/ip/hotspot/active/print') ?: [];
        $hsBindings = $api->executeCommand('/ip/hotspot/ip-binding/print') ?: [];

        // Identify live active usernames
        $activePppUsers = is_array($pppActive) ? array_column($pppActive, 'name') : [];
        $activeHsUsers = is_array($hsActive) ? array_column($hsActive, 'user') : [];

        return [
            'success'          => true,
            'host'             => $host,
            'ppp_secrets_count'=> count($pppSecrets),
            'ppp_active_count' => count($pppActive),
            'ppp_profiles'     => is_array($pppProfiles) ? array_column($pppProfiles, 'name') : [],
            'hs_users_count'   => count($hsUsers),
            'hs_active_count'  => count($hsActive),
            'hs_profiles'      => is_array($hsProfiles) ? array_column($hsProfiles, 'name') : [],
            'hs_bindings_count'=> count($hsBindings),
            'sample_ppp'       => array_slice($pppSecrets, 0, 10),
            'sample_hs'        => array_slice($hsUsers, 0, 10),
            'active_ppp_users' => $activePppUsers,
            'active_hs_users'  => $activeHsUsers,
        ];
    }

    /**
     * Execute Zero-Downtime Migration from a live MikroTik router.
     *
     * Guarantee: NO active sessions are dropped (/ppp active and /ip hotspot active are untouched).
     * Clients browse uninterrupted during and after the migration.
     */
    public function migrateFromRouter(
        int $targetTenantId,
        int $routerId,
        string $host,
        int $port,
        string $user,
        string $password,
        array $options = []
    ): array {
        $api = new MikrotikApiService($host, $port, $user, $password, 8);

        $router = Router::withoutGlobalScopes()->find($routerId);
        $routerName = $router ? ($router->name ?: $router->router_name) : 'Migrated Router';

        // 1. Fetch all router data
        $pppSecrets = $api->executeCommand('/ppp/secret/print') ?: [];
        $pppProfiles = $api->executeCommand('/ppp/profile/print') ?: [];
        $pppActive = $api->executeCommand('/ppp/active/print') ?: [];
        $hsUsers = $api->executeCommand('/ip/hotspot/user/print') ?: [];
        $hsProfiles = $api->executeCommand('/ip/hotspot/user/profile/print') ?: [];

        $activePppMap = [];
        if (is_array($pppActive)) {
            foreach ($pppActive as $act) {
                $u = trim($act['name'] ?? '');
                if ($u) {
                    $activePppMap[$u] = $act;
                }
            }
        }

        $migratedPpp = 0;
        $skippedPpp = 0;
        $migratedHs = 0;
        $skippedHs = 0;
        $createdPackages = 0;

        DB::beginTransaction();
        try {
            // 2. Auto-map or create packages from PPP Profiles
            $profileToPackageMap = [];
            if (is_array($pppProfiles)) {
                foreach ($pppProfiles as $prof) {
                    $pName = trim($prof['name'] ?? '');
                    if (!$pName || in_array($pName, ['default', 'default-encryption'])) continue;

                    $rateLimit = $prof['rate-limit'] ?? '10M/10M';
                    $existingPkg = PackageList::withoutGlobalScopes()
                        ->where('tenant_id', $targetTenantId)
                        ->where(function ($q) use ($pName) {
                            $q->where('name', $pName)
                              ->orWhere('package_name', $pName);
                        })
                        ->first();

                    if (!$existingPkg) {
                        $newPkg = PackageList::withoutGlobalScopes()->create([
                            'tenant_id'          => $targetTenantId,
                            'name'               => $pName,
                            'package_name'       => $pName,
                            'package_price'      => 1500.00,
                            'speed_limit'        => $rateLimit,
                            'package_speed'      => $rateLimit,
                            'validity'           => '1 month',
                            'duration'           => 30,
                            'duration_unit'      => 'days',
                            'description'        => "Auto-migrated from MikroTik profile {$pName}",
                            'is_active'          => 1,
                            'type'               => 'pppoe',
                        ]);
                        $profileToPackageMap[$pName] = $newPkg->id;
                        $createdPackages++;
                    } else {
                        $profileToPackageMap[$pName] = $existingPkg->id;
                    }
                }
            }

            // 3. Migrate PPPoE Secrets without kicking live sessions
            $lastCustomerNum = (int) (CustomersInfo::withoutGlobalScopes()->max('id') ?? 1000);

            if (is_array($pppSecrets)) {
                foreach ($pppSecrets as $sec) {
                    $username = trim($sec['name'] ?? '');
                    $plainPass = trim($sec['password'] ?? '');
                    $profile = trim($sec['profile'] ?? 'default');
                    $comment = trim($sec['comment'] ?? '');
                    $remoteIp = trim($sec['remote-address'] ?? '');

                    if (!$username) continue;

                    // Check if already in HSmart
                    $existingPpp = PPPSecrets::withoutGlobalScopes()
                        ->where('username', $username)
                        ->where('tenant_id', $targetTenantId)
                        ->first();

                    if ($existingPpp) {
                        $skippedPpp++;
                        continue;
                    }

                    $lastCustomerNum++;
                    $customerUniqueId = 'CUST-' . str_pad((string)$lastCustomerNum, 5, '0', STR_PAD_LEFT);

                    // Extract mobile number from username, comment, or caller-id if present
                    $mobile = $this->extractPhoneNumber($username, $comment);
                    $packageId = $profileToPackageMap[$profile] ?? null;

                    // Is user currently active/online right now on the router?
                    $isOnline = isset($activePppMap[$username]);
                    $activeSession = $activePppMap[$username] ?? null;

                    // A. Create Customer Profile
                    $customer = CustomersInfo::withoutGlobalScopes()->create([
                        'tenant_id'          => $targetTenantId,
                        'customer_unique_id' => $customerUniqueId,
                        'customer_name'      => $comment ? preg_replace('/[0-9+]/', '', $comment) : $username,
                        'mobile'             => $mobile ?: '254700000000',
                        'service_type'       => 'pppoe',
                        'package_id'         => $packageId,
                        'router_id'          => $routerId,
                        'status'             => $isOnline ? 'active' : 'active',
                        'connection_date'    => now(),
                    ]);

                    // B. Create PPP Secret record
                    $pppRecord = PPPSecrets::withoutGlobalScopes()->create([
                        'tenant_id'      => $targetTenantId,
                        'customer_id'    => $customer->id,
                        'router_id'      => $routerId,
                        'router_name'    => $routerName,
                        'username'       => $username,
                        'password'       => $plainPass,
                        'profile'        => $profile,
                        'service'        => 'pppoe',
                        'status'         => 'active',
                        'remote_address' => $remoteIp ?: ($activeSession['address'] ?? null),
                    ]);

                    $customer->update(['ppp_user_id' => $pppRecord->id]);

                    // C. Create Billing Profile
                    BillingInfo::withoutGlobalScopes()->create([
                        'tenant_id'          => $targetTenantId,
                        'customer_id'        => $customer->id,
                        'monthly_rent'       => 1500.00,
                        'auto_disable_date'  => now()->addMonth()->toDateString(),
                        'auto_disable_month' => 1,
                        'payment_status'     => 'paid',
                    ]);

                    $migratedPpp++;
                }
            }

            // 4. Migrate Hotspot Users / Vouchers
            if (is_array($hsUsers)) {
                foreach ($hsUsers as $hu) {
                    $hName = trim($hu['name'] ?? '');
                    $hPass = trim($hu['password'] ?? $hName);
                    $hProf = trim($hu['profile'] ?? 'default');
                    $hLimitUptime = trim($hu['limit-uptime'] ?? '1h');
                    $hComment = trim($hu['comment'] ?? '');

                    if (!$hName) continue;

                    $existsHs = HotspotVoucher::withoutGlobalScopes()
                        ->where('username', $hName)
                        ->where('tenant_id', $targetTenantId)
                        ->exists();

                    if ($existsHs) {
                        $skippedHs++;
                        continue;
                    }

                    $durationParsed = MikrotikDuration::parse($hLimitUptime);

                    HotspotVoucher::withoutGlobalScopes()->create([
                        'tenant_id'         => $targetTenantId,
                        'router_id'         => $routerId,
                        'router_name'       => $routerName,
                        'code'              => $hName,
                        'username'          => $hName,
                        'password'          => $hPass,
                        'profile'           => $hProf,
                        'price'             => 20.00,
                        'duration_hours'    => (float) round($durationParsed['seconds'] / 3600, 2),
                        'uptime_limit'      => $durationParsed['seconds'],
                        'validity_duration' => $durationParsed['mikrotik'],
                        'status'            => 'unused',
                    ]);

                    $migratedHs++;
                }
            }

            DB::commit();

            return [
                'success'           => true,
                'router_name'       => $routerName,
                'migrated_pppoe'    => $migratedPpp,
                'skipped_pppoe'     => $skippedPpp,
                'migrated_hotspot'  => $migratedHs,
                'skipped_hotspot'   => $skippedHs,
                'created_packages'  => $createdPackages,
                'zero_downtime'     => true,
                'message'           => "Zero-downtime migration completed! Migrated {$migratedPpp} PPPoE subscribers and {$migratedHs} Hotspot accounts. All live users remained online without interruption.",
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[ZeroDowntimeMigration] Failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse and import a MikroTik export .rsc script file with zero downtime.
     */
    public function importRscFile(int $targetTenantId, int $routerId, string $rscContent): array
    {
        $lines = explode("\n", $rscContent);
        $pppSecrets = [];
        $hsUsers = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;

            // PPPoE Secret line
            if (str_contains($line, '/ppp secret add') || (str_contains($line, 'add') && str_contains($line, 'service=pppoe'))) {
                $item = $this->parseMikrotikCommandLine($line);
                if (!empty($item['name'])) {
                    $pppSecrets[] = $item;
                }
            }

            // Hotspot User line
            if (str_contains($line, '/ip hotspot user add') || (str_contains($line, 'add') && str_contains($line, 'limit-uptime'))) {
                $item = $this->parseMikrotikCommandLine($line);
                if (!empty($item['name'])) {
                    $hsUsers[] = $item;
                }
            }
        }

        $migratedPpp = 0;
        $migratedHs = 0;

        DB::beginTransaction();
        try {
            $router = Router::withoutGlobalScopes()->find($routerId);
            $routerName = $router ? ($router->name ?: $router->router_name) : 'Imported Router';

            $lastNum = (int) (CustomersInfo::withoutGlobalScopes()->max('id') ?? 1000);

            foreach ($pppSecrets as $sec) {
                $u = $sec['name'];
                $p = $sec['password'] ?? $u;
                $prof = $sec['profile'] ?? 'default';
                $comm = $sec['comment'] ?? '';

                if (PPPSecrets::withoutGlobalScopes()->where('username', $u)->where('tenant_id', $targetTenantId)->exists()) {
                    continue;
                }

                $lastNum++;
                $cust = CustomersInfo::withoutGlobalScopes()->create([
                    'tenant_id'          => $targetTenantId,
                    'customer_unique_id' => 'CUST-' . str_pad((string)$lastNum, 5, '0', STR_PAD_LEFT),
                    'customer_name'      => $comm ?: $u,
                    'mobile'             => $this->extractPhoneNumber($u, $comm) ?: '254700000000',
                    'service_type'       => 'pppoe',
                    'router_id'          => $routerId,
                    'status'             => 'active',
                    'connection_date'    => now(),
                ]);

                $ppp = PPPSecrets::withoutGlobalScopes()->create([
                    'tenant_id'   => $targetTenantId,
                    'customer_id' => $cust->id,
                    'router_id'   => $routerId,
                    'router_name' => $routerName,
                    'username'    => $u,
                    'password'    => $p,
                    'profile'     => $prof,
                    'service'     => 'pppoe',
                    'status'      => 'active',
                ]);

                $cust->update(['ppp_user_id' => $ppp->id]);

                BillingInfo::withoutGlobalScopes()->create([
                    'tenant_id'          => $targetTenantId,
                    'customer_id'        => $cust->id,
                    'monthly_rent'       => 1500.00,
                    'auto_disable_date'  => now()->addMonth()->toDateString(),
                    'auto_disable_month' => 1,
                    'payment_status'     => 'paid',
                ]);

                $migratedPpp++;
            }

            foreach ($hsUsers as $hu) {
                $u = $hu['name'];
                $p = $hu['password'] ?? $u;
                $prof = $hu['profile'] ?? 'default';
                $limit = $hu['limit-uptime'] ?? '1h';

                if (HotspotVoucher::withoutGlobalScopes()->where('username', $u)->where('tenant_id', $targetTenantId)->exists()) {
                    continue;
                }

                $dur = MikrotikDuration::parse($limit);

                HotspotVoucher::withoutGlobalScopes()->create([
                    'tenant_id'         => $targetTenantId,
                    'router_id'         => $routerId,
                    'router_name'       => $routerName,
                    'code'              => $u,
                    'username'          => $u,
                    'password'          => $p,
                    'profile'           => $prof,
                    'price'             => 20.00,
                    'duration_hours'    => (float) round($dur['seconds'] / 3600, 2),
                    'uptime_limit'      => $dur['seconds'],
                    'validity_duration' => $dur['mikrotik'],
                    'status'            => 'unused',
                ]);

                $migratedHs++;
            }

            DB::commit();

            return [
                'success'          => true,
                'migrated_pppoe'   => $migratedPpp,
                'migrated_hotspot' => $migratedHs,
                'zero_downtime'    => true,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Parse key=value tokens from a MikroTik script line.
     */
    protected function parseMikrotikCommandLine(string $line): array
    {
        $tokens = [];
        preg_match_all('/([a-zA-Z0-9_-]+)=("([^"]*)"|([^\s]+))/', $line, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $key = $m[1];
            $val = isset($m[3]) && $m[3] !== '' ? $m[3] : ($m[4] ?? '');
            $tokens[$key] = $val;
        }
        return $tokens;
    }

    /**
     * Extract a valid 10/12-digit mobile number from string.
     */
    protected function extractPhoneNumber(string ...$sources): ?string
    {
        foreach ($sources as $s) {
            if (preg_match('/(254[0-9]{9}|07[0-9]{8}|01[0-9]{8})/', $s, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
