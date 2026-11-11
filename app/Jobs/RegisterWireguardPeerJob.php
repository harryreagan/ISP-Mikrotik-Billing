<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\WireGuardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterWireguardPeerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $routerId;
    public string $publicKey;
    public string $vpnIp;

    /**
     * Create a new job instance.
     */
    public function __construct(int $routerId, string $publicKey, string $vpnIp)
    {
        $this->routerId  = $routerId;
        $this->publicKey = trim($publicKey);
        $this->vpnIp     = trim($vpnIp);
    }

    /**
     * Execute the job: register peer on the VPS WireGuard interface.
     * Uses WireGuardService::addPeer() which executes `sudo wg set <iface> peer <key> allowed-ips <ip>/32`
     * and persists the peer to /etc/wireguard/<iface>.conf.
     */
    public function handle(): void
    {
        $router = Router::withoutGlobalScopes()->find($this->routerId);
        if (!$router) {
            Log::warning("[RegisterWireguardPeerJob] Router #{$this->routerId} not found.");
            return;
        }

        $tenantId = $router->tenant_id ?? 1;
        \App\Services\Tenant\TenantContext::runWithTenant((int) $tenantId, function () use ($router) {
            try {
                $success = WireGuardService::addPeer($this->publicKey, $this->vpnIp);

                if ($success) {
                    $router->update([
                        'vpn_status' => 'peer_registered',
                        'notes'      => ($router->notes ? $router->notes . " | " : "") . "WireGuard peer registered successfully at " . now()->toDateTimeString(),
                    ]);
                    Log::info("[RegisterWireguardPeerJob] Successfully registered peer {$this->publicKey} ({$this->vpnIp}) for router #{$this->routerId}");
                } else {
                    $router->update([
                        'vpn_status' => 'peer_failed',
                        'notes'      => ($router->notes ? $router->notes . " | " : "") . "WireGuard peer registration warning at " . now()->toDateTimeString(),
                    ]);
                    Log::warning("[RegisterWireguardPeerJob] WireGuard peer registration reported non-fatal status for router #{$this->routerId}");
                }
            } catch (\Throwable $e) {
                $router->update([
                    'vpn_status' => 'peer_failed',
                    'notes'      => ($router->notes ? $router->notes . " | " : "") . "WireGuard error: " . $e->getMessage(),
                ]);
                Log::error("[RegisterWireguardPeerJob] Exception registering peer for router #{$this->routerId}: " . $e->getMessage());
            }
        });
    }
}

