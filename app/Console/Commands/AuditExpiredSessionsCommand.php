<?php

namespace App\Console\Commands;

use App\Models\HotspotSale;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\MikrotikDuration;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class AuditExpiredSessionsCommand extends Command
{
    protected $signature = 'isp:audit-expired-sessions {--router= : Filter by specific router ID} {--kick : Forcefully disconnect expired sessions discovered}';

    protected $description = 'Audit active hotspot sessions on routers and report any sessions still active past their expiry.';

    public function handle(): int
    {
        $routerId = $this->option('router') ? (int) $this->option('router') : null;
        $shouldKick = (bool) $this->option('kick');

        $routers = Router::withoutGlobalScopes()
            ->when($routerId, fn($q) => $q->where('id', $routerId))
            ->where(function ($q) {
                $q->where('status', 'online')
                  ->orWhere('action', 'connected')
                  ->orWhereNotNull('vpn_ip');
            })
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('No online or connected routers found to audit.');
            return Command::SUCCESS;
        }

        $now = Carbon::now('Africa/Nairobi');
        $rows = [];
        $expiredCount = 0;

        foreach ($routers as $router) {
            $host = $router->vpn_ip ?: ($router->last_known_ip ?: $router->ip);
            if (!$host) continue;

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
                $activeSessions = $api->executeCommand('/ip/hotspot/active/print');
                if (!is_array($activeSessions) || empty($activeSessions)) {
                    continue;
                }

                $hotspotUsers = $api->executeCommand('/ip/hotspot/user/print');
                $userMap = [];
                if (is_array($hotspotUsers)) {
                    foreach ($hotspotUsers as $hu) {
                        $uName = strtolower(trim($hu['name'] ?? ''));
                        if ($uName) {
                            $userMap[$uName] = $hu;
                        }
                    }
                }

                foreach ($activeSessions as $session) {
                    $username = trim($session['user'] ?? '');
                    $ip = $session['address'] ?? ($session['ip'] ?? '-');
                    $mac = $session['mac-address'] ?? '-';
                    $uptime = $session['uptime'] ?? '0s';
                    $sessionId = $session['.id'] ?? null;

                    $isExpired = false;
                    $reason = 'Active (Valid)';

                    $rUser = $userMap[strtolower($username)] ?? null;
                    $limitUptime = $rUser['limit-uptime'] ?? 'None';

                    if (!empty($rUser['limit-uptime'])) {
                        $limitSec = MikrotikDuration::parse($rUser['limit-uptime'])['seconds'];
                        $uptimeSec = MikrotikDuration::parse($uptime)['seconds'];
                        if ($limitSec > 0 && $uptimeSec >= $limitSec) {
                            $isExpired = true;
                            $reason = "Uptime limit exceeded ({$uptime} >= {$rUser['limit-uptime']})";
                        }
                    }

                    // Check DB expiry
                    $dbSale = HotspotSale::withoutGlobalScopes()
                        ->where(function ($q) use ($username, $mac) {
                            $q->where('voucher_code', $username)
                              ->orWhere('username', $username)
                              ->orWhere('mac_address', $mac);
                        })
                        ->latest('expires_at')
                        ->first();

                    if (!$isExpired && $dbSale && $dbSale->expires_at && $now->greaterThanOrEqualTo($dbSale->expires_at)) {
                        $isExpired = true;
                        $reason = "DB sale expired at " . $dbSale->expires_at->toDateTimeString();
                    }

                    if ($isExpired) {
                        $expiredCount++;
                        if ($shouldKick && $sessionId) {
                            try {
                                $api->executeCommand('/ip/hotspot/active/remove', ['.id' => $sessionId]);
                                if (!empty($rUser['.id'])) {
                                    $api->executeCommand('/ip/hotspot/user/remove', ['.id' => $rUser['.id']]);
                                }
                                $reason .= ' [KICKED]';
                            } catch (\Throwable $kEx) {
                                $reason .= ' [KICK FAILED: ' . $kEx->getMessage() . ']';
                            }
                        }
                    }

                    $rows[] = [
                        $router->name,
                        $username,
                        $ip,
                        $mac,
                        $uptime,
                        $limitUptime,
                        $isExpired ? '<fg=red>EXPIRED</>' : '<fg=green>OK</>',
                        $reason,
                    ];
                }
            } catch (\Throwable $e) {
                $this->error("Failed to inspect router {$router->name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->table(
            ['Router', 'User / Voucher', 'IP', 'MAC', 'Uptime', 'Limit', 'Status', 'Verdict / Action'],
            $rows
        );

        $this->info("Audit complete. Total active sessions: " . count($rows) . ", Expired sessions: {$expiredCount}");

        return Command::SUCCESS;
    }
}

