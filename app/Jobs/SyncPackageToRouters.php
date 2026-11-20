<?php

namespace App\Jobs;

use App\Models\HotspotSale;
use App\Models\PackageList;
use App\Models\PrepaidUser;
use App\Models\Router;
use App\Services\CaptivePortalTemplates;
use App\Services\MikrotikApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncPackageToRouters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Exponential retry backoff in seconds.
     */
    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public function __construct(
        public int $packageId,
        public string $action = 'upsert',
        public ?string $oldProfileName = null,
        public ?int $tenantId = null,
        public array $deletedSnapshot = []
    ) {}

    public function handle(): void
    {
        if ($this->action === 'delete') {
            $this->handleDelete();
            return;
        }

        $package = PackageList::withoutGlobalScopes()->find($this->packageId);
        if (!$package) {
            return;
        }

        $tenantId = (int) ($package->tenant_id ?: $this->tenantId ?: 1);

        // Fetch assigned non-deleted routers scoped strictly to this tenant
        $routers = $package->routers()
            ->withoutGlobalScopes()
            ->where('routers.tenant_id', $tenantId)
            ->whereNull('routers.deleted_at')
            ->get();

        // If no specific routers attached, link & sync online/active routers belonging to this tenant
        if ($routers->isEmpty()) {
            $routers = Router::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'online')
                ->get();

            if ($routers->isEmpty()) {
                $routers = Router::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->get();
            }

            if ($routers->isNotEmpty()) {
                $package->routers()->sync($routers->pluck('id')->all());
            }
        }

        if ($routers->isEmpty()) {
            $package->updateQuietly([
                'sync_status'     => 'pending',
                'last_sync_error' => 'No routers attached to this tenant yet.',
            ]);
            return;
        }

        $pkgType = strtolower((string) ($package->type ?: 'hotspot'));
        if (!in_array($pkgType, ['hotspot', 'pppoe'], true)) {
            $pkgType = 'hotspot';
        }

        $profileName = trim((string) $package->package);
        $rateLimit = $package->getRateLimitString();
        $sessionTimeout = $package->getSessionTimeoutString();
        $sharedUsers = (string) max(1, (int) ($package->shared_users ?: 1));
        $localAddr = $package->mikrotik_local_address ?: '10.0.0.1';
        $remoteAddr = $package->mikrotik_remote_address ?: 'pppoe-pool';

        $candidateNames = $this->buildCandidateNames($profileName, $pkgType, $this->oldProfileName);
        $candidateLower = array_map('strtolower', $candidateNames);

        // Find active users/usernames mapped to this package in DB for updating
        $dbHotspotUsernames = [];
        $dbPppUsernames = [];
        try {
            if (Schema::hasTable('prepaid_users')) {
                $prepaidRows = PrepaidUser::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($package, $candidateNames) {
                        $q->where('package_id', $package->id)
                          ->orWhereIn('plan_name', $candidateNames);
                    })
                    ->whereIn('status', ['online', 'offline', 'active'])
                    ->get(['username', 'type']);

                foreach ($prepaidRows as $pr) {
                    if ($pr->type === 'pppoe') {
                        $dbPppUsernames[] = strtolower(trim((string) $pr->username));
                    } else {
                        $dbHotspotUsernames[] = strtolower(trim((string) $pr->username));
                    }
                }
            }

            if ($pkgType === 'hotspot' && Schema::hasTable('hotspot_sales')) {
                $salesRows = HotspotSale::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('profile', $candidateNames)
                    ->whereIn('status', ['completed', 'active', 'pending_router'])
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->get(['voucher_code', 'username']);

                foreach ($salesRows as $sr) {
                    if (!empty($sr->voucher_code)) {
                        $dbHotspotUsernames[] = strtolower(trim((string) $sr->voucher_code));
                    }
                    if (!empty($sr->username)) {
                        $dbHotspotUsernames[] = strtolower(trim((string) $sr->username));
                    }
                }
            }

            if ($pkgType === 'pppoe' && Schema::hasTable('customers_infos')) {
                $pppCustUsers = DB::table('customers_infos')
                    ->join('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
                    ->where('customers_infos.tenant_id', $tenantId)
                    ->where('customers_infos.package_id', $package->id)
                    ->pluck('p_p_p_secrets.username')
                    ->toArray();

                foreach ($pppCustUsers as $u) {
                    $dbPppUsernames[] = strtolower(trim((string) $u));
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[SyncPackageToRouters] Failed loading DB users for package #{$package->id}: " . $e->getMessage());
        }

        $dbHotspotUsernames = array_values(array_unique(array_filter($dbHotspotUsernames)));
        $dbPppUsernames = array_values(array_unique(array_filter($dbPppUsernames)));

        $syncedCount = 0;
        $failedCount = 0;
        $lastError = null;

        foreach ($routers as $router) {
            try {
                $api = $this->connectRouter($router);

                if ($pkgType === 'hotspot') {
                    // 1. Create or update Hotspot user profile (match by name, never duplicate)
                    $allProfiles = $this->execOrFail($api, '/ip/hotspot/user/profile/print');
                    $matchedIds = [];
                    if (is_array($allProfiles)) {
                        foreach ($allProfiles as $prof) {
                            $pName = strtolower(trim((string) ($prof['name'] ?? '')));
                            if ($pName !== '' && in_array($pName, $candidateLower, true) && !empty($prof['.id'])) {
                                $matchedIds[] = [
                                    '.id'  => $prof['.id'],
                                    'name' => $prof['name'],
                                ];
                            }
                        }
                    }

                    $profilePayload = [
                        'rate-limit'         => $rateLimit,
                        'shared-users'       => $sharedUsers,
                        'session-timeout'    => $sessionTimeout,
                        'idle-timeout'       => '5m',
                        'status-autorefresh' => '1m',
                    ];

                    if (!empty($matchedIds)) {
                        foreach ($matchedIds as $idx => $m) {
                            $setPayload = $profilePayload;
                            if ($idx === 0) {
                                $setPayload['name'] = $profileName;
                            }
                            $this->execOrFail($api, '/ip/hotspot/user/profile/set', $setPayload, ['.id' => $m['.id']]);
                        }
                    } else {
                        $this->execOrFail($api, '/ip/hotspot/user/profile/add', array_merge($profilePayload, [
                            'name'              => $profileName,
                            'transparent-proxy' => 'yes',
                        ]));
                    }

                    // 2. Update limit-uptime and profile on existing active hotspot users on this package
                    $routerUsers = $this->execOrFail($api, '/ip/hotspot/user/print');
                    $affectedHotspotNames = [];
                    if (is_array($routerUsers)) {
                        foreach ($routerUsers as $ru) {
                            $ruId = $ru['.id'] ?? null;
                            $ruName = strtolower(trim((string) ($ru['name'] ?? '')));
                            $ruProf = strtolower(trim((string) ($ru['profile'] ?? '')));
                            if (!$ruId || $ruName === '' || $ruName === 'default-trial') {
                                continue;
                            }
                            if (in_array($ruProf, $candidateLower, true) || in_array($ruName, $dbHotspotUsernames, true)) {
                                $this->execOrFail($api, '/ip/hotspot/user/set', [
                                    'profile'      => $profileName,
                                    'limit-uptime' => $sessionTimeout,
                                ], ['.id' => $ruId]);
                                $affectedHotspotNames[] = $ruName;
                            }
                        }
                    }

                    // 3. Apply new speed immediately to any active Hotspot session dynamic simple queue
                    if (!empty($affectedHotspotNames)) {
                        try {
                            $queues = $api->executeCommand('/queue/simple/print');
                            if (is_array($queues)) {
                                foreach ($queues as $q) {
                                    $qName = strtolower((string) ($q['name'] ?? ''));
                                    $qId = $q['.id'] ?? null;
                                    if (!$qId) continue;
                                    foreach ($affectedHotspotNames as $hName) {
                                        if (str_contains($qName, '<hotspot-' . $hName . '>')) {
                                            $api->executeCommand('/queue/simple/set', [
                                                'max-limit' => $rateLimit,
                                            ], ['.id' => $qId]);
                                            break;
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $qe) {}
                    }

                    // 4. Push updated captive portal HTML so prices/speeds/names reflect immediately
                    try {
                        CaptivePortalTemplates::pushPortalFilesOnly($router);
                    } catch (\Throwable $pe) {
                        Log::warning("[SyncPackageToRouters] Portal push warning for router #{$router->id}: " . $pe->getMessage());
                    }
                } else {
                    // 1. Create or update PPP profile (match by name, never duplicate)
                    $allPppProfiles = $this->execOrFail($api, '/ppp/profile/print');
                    $matchedPppIds = [];
                    if (is_array($allPppProfiles)) {
                        foreach ($allPppProfiles as $prof) {
                            $pName = strtolower(trim((string) ($prof['name'] ?? '')));
                            if ($pName !== '' && in_array($pName, $candidateLower, true) && !empty($prof['.id'])) {
                                $matchedPppIds[] = $prof['.id'];
                            }
                        }
                    }

                    $pppPayload = [
                        'rate-limit'     => $rateLimit,
                        'local-address'  => $localAddr,
                        'remote-address' => $remoteAddr,
                    ];

                    if (!empty($matchedPppIds)) {
                        foreach ($matchedPppIds as $idx => $id) {
                            $setPayload = $pppPayload;
                            if ($idx === 0) {
                                $setPayload['name'] = $profileName;
                            }
                            $this->execOrFail($api, '/ppp/profile/set', $setPayload, ['.id' => $id]);
                        }
                    } else {
                        $this->execOrFail($api, '/ppp/profile/add', array_merge($pppPayload, [
                            'name' => $profileName,
                        ]));
                    }

                    // 2. Update existing /ppp/secret entries on that profile
                    $pppSecrets = $this->execOrFail($api, '/ppp/secret/print');
                    $affectedPppUsers = [];
                    if (is_array($pppSecrets)) {
                        foreach ($pppSecrets as $sec) {
                            $secId = $sec['.id'] ?? null;
                            $secName = strtolower(trim((string) ($sec['name'] ?? '')));
                            $secProf = strtolower(trim((string) ($sec['profile'] ?? '')));
                            if (!$secId || $secName === '') {
                                continue;
                            }
                            if (in_array($secProf, $candidateLower, true) || in_array($secName, $dbPppUsernames, true)) {
                                $this->execOrFail($api, '/ppp/secret/set', [
                                    'profile' => $profileName,
                                ], ['.id' => $secId]);
                                $affectedPppUsers[] = $secName;
                            }
                        }
                    }

                    // 3. Kick active /ppp/active sessions so the new rate-limit applies immediately on reconnect
                    if (!empty($affectedPppUsers)) {
                        $pppActives = $this->execOrFail($api, '/ppp/active/print');
                        if (is_array($pppActives)) {
                            foreach ($pppActives as $act) {
                                $actId = $act['.id'] ?? null;
                                $actName = strtolower(trim((string) ($act['name'] ?? '')));
                                if ($actId && in_array($actName, $affectedPppUsers, true)) {
                                    $this->execOrFail($api, '/ppp/active/remove', [], ['.id' => $actId]);
                                }
                            }
                        }
                    }
                }

                // Mark pivot row as synced
                $package->routers()->updateExistingPivot($router->id, [
                    'sync_status' => 'synced',
                    'sync_error'  => null,
                    'synced_at'   => now(),
                ]);
                $syncedCount++;
            } catch (\Throwable $e) {
                $errMsg = substr($e->getMessage(), 0, 250);
                $lastError = "Router {$router->name}: {$errMsg}";
                Log::warning("[SyncPackageToRouters] Failed syncing package #{$package->id} to router #{$router->id} ({$router->name}): {$errMsg}");

                try {
                    $package->routers()->updateExistingPivot($router->id, [
                        'sync_status' => 'failed',
                        'sync_error'  => $errMsg,
                    ]);
                } catch (\Throwable $pe) {}

                $failedCount++;
            }
        }

        if ($failedCount === 0 && $syncedCount > 0) {
            $package->updateQuietly([
                'sync_status'     => 'synced',
                'last_sync_error' => null,
                'last_synced_at'  => now(),
            ]);
        } else {
            $package->updateQuietly([
                'sync_status'     => $failedCount > 0 ? 'failed' : 'pending',
                'last_sync_error' => $lastError,
                'last_synced_at'  => now(),
            ]);

            // Retry with backoff when dispatched on queue worker
            if ($failedCount > 0 && $this->job !== null && $this->attempts() < $this->tries) {
                $delay = $this->backoff[$this->attempts() - 1] ?? 30;
                $this->release($delay);
            }
        }
    }

    protected function handleDelete(): void
    {
        $tenantId = (int) ($this->tenantId ?: ($this->deletedSnapshot['tenant_id'] ?? 1));
        $profileName = trim((string) ($this->deletedSnapshot['package'] ?? $this->oldProfileName ?? ''));
        $pkgType = strtolower((string) ($this->deletedSnapshot['type'] ?? 'hotspot'));
        $routerIds = $this->deletedSnapshot['router_ids'] ?? [];

        if ($profileName === '') {
            return;
        }

        $routersQuery = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at');
        if (!empty($routerIds)) {
            $routersQuery->whereIn('id', $routerIds);
        }
        $routers = $routersQuery->get();

        $candidateLower = array_map('strtolower', $this->buildCandidateNames($profileName, $pkgType, null));
        $path = $pkgType === 'pppoe' ? '/ppp/profile' : '/ip/hotspot/user/profile';

        foreach ($routers as $router) {
            try {
                $api = $this->connectRouter($router);
                $profiles = $api->executeCommand($path . '/print');
                if (is_array($profiles)) {
                    foreach ($profiles as $prof) {
                        $pName = strtolower(trim((string) ($prof['name'] ?? '')));
                        if ($pName !== '' && in_array($pName, $candidateLower, true) && !empty($prof['.id'])) {
                            $api->executeCommand($path . '/remove', [], ['.id' => $prof['.id']]);
                        }
                    }
                }
                if ($pkgType === 'hotspot') {
                    CaptivePortalTemplates::pushPortalFilesOnly($router);
                }
            } catch (\Throwable $e) {
                Log::warning("[SyncPackageToRouters] Delete profile warning on router #{$router->id}: " . $e->getMessage());
            }
        }
    }

    protected function connectRouter(Router $router): MikrotikApiService
    {
        $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
        $apiUser = $router->api_user ?: ($router->username ?: 'admin');
        if (empty($host) || empty($apiUser)) {
            throw new \RuntimeException('Router IP or API username not configured.');
        }

        $port = (int) ($router->api_port ?: 8728);
        $password = CaptivePortalTemplates::decryptRouterPassword($router->api_password ?: $router->password);

        return new MikrotikApiService($host, $port, $apiUser, $password, 8);
    }

    protected function execOrFail(MikrotikApiService $api, string $command, array $params = [], array $filters = [])
    {
        $res = $api->executeCommand($command, $params, $filters);
        if (is_string($res) && str_starts_with($res, 'Error:')) {
            throw new \RuntimeException($res);
        }
        return $res;
    }

    protected function buildCandidateNames(string $name, string $type, ?string $oldName): array
    {
        $prefix = $type === 'pppoe' ? 'ppp_' : 'hs_';
        $candidates = [
            trim($name),
            $prefix . preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($name)),
        ];
        if (!empty($oldName)) {
            $oldTrim = trim($oldName);
            $candidates[] = $oldTrim;
            $candidates[] = $prefix . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $oldTrim);
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}
