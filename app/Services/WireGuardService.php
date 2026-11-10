<?php

namespace App\Services;

use App\Models\Router;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WireGuardService
{
    /**
     * Generate an X25519 keypair for WireGuard (base64).
     */
    public static function generateKeyPair(): array
    {
        if (function_exists('sodium_crypto_box_keypair') && function_exists('sodium_crypto_box_secretkey')) {
            try {
                $keypair = sodium_crypto_box_keypair();
                $secretKey = sodium_crypto_box_secretkey($keypair);

                // WireGuard key clamping (RFC 7748)
                $secretKey[0] = chr(ord($secretKey[0]) & 248);
                $secretKey[31] = chr((ord($secretKey[31]) & 127) | 64);

                $publicKey = sodium_crypto_scalarmult_base($secretKey);

                return [
                    'private_key' => base64_encode($secretKey),
                    'public_key'  => base64_encode($publicKey),
                ];
            } catch (\Throwable $e) {
                Log::warning('[WireGuardService] Sodium keypair failed, attempting fallback: ' . $e->getMessage());
            }
        }

        // CLI fallback
        try {
            $privKey = trim((string) @shell_exec('wg genkey 2>/dev/null'));
            if (!empty($privKey)) {
                $pubKey = trim((string) @shell_exec("echo " . escapeshellarg($privKey) . " | wg pubkey 2>/dev/null"));
                if (!empty($pubKey)) {
                    return [
                        'private_key' => $privKey,
                        'public_key'  => $pubKey,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[WireGuardService] CLI key generation failed: ' . $e->getMessage());
        }

        // Random fallback (usable in test environments)
        $rawSecret = random_bytes(32);
        $rawSecret[0] = chr(ord($rawSecret[0]) & 248);
        $rawSecret[31] = chr((ord($rawSecret[31]) & 127) | 64);
        $rawPublic = function_exists('sodium_crypto_scalarmult_base')
            ? sodium_crypto_scalarmult_base($rawSecret)
            : random_bytes(32);

        return [
            'private_key' => base64_encode($rawSecret),
            'public_key'  => base64_encode($rawPublic),
        ];
    }

    /**
     * Allocate the next available VPN IP address from vpn_ip_pool (10.10.0.0/16).
     */
    public static function allocateVpnIp(int $routerId): string
    {
        return DB::transaction(function () use ($routerId) {
            // Check if router already has an IP assigned
            $existing = DB::table('vpn_ip_pool')->where('router_id', $routerId)->first();
            if ($existing) {
                return $existing->ip_address;
            }

            // Lock next available
            $slot = DB::table('vpn_ip_pool')
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            if ($slot) {
                DB::table('vpn_ip_pool')->where('id', $slot->id)->update([
                    'status'      => 'assigned',
                    'router_id'   => $routerId,
                    'assigned_at' => now(),
                    'updated_at'  => now(),
                ]);
                return $slot->ip_address;
            }

            // Fallback: Calculate highest IP currently assigned
            $maxIp = DB::table('routers')->whereNotNull('vpn_ip')->pluck('vpn_ip')->map(function ($ip) {
                return ip2long($ip);
            })->max();

            $baseLong = $maxIp ? ($maxIp + 1) : ip2long('10.10.0.50');
            $nextIp = long2ip($baseLong);

            return $nextIp;
        });
    }

    /**
     * Release VPN IP address back to available pool.
     */
    public static function releaseVpnIp(int $routerId): void
    {
        try {
            DB::table('vpn_ip_pool')->where('router_id', $routerId)->update([
                'status'      => 'available',
                'router_id'   => null,
                'assigned_at' => null,
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[WireGuardService] Error releasing VPN IP for router {$routerId}: " . $e->getMessage());
        }
    }

    /**
     * Add or update peer on the VPS WireGuard interface (wg0).
     */
    public static function addPeer(string $publicKey, string $vpnIp): bool
    {
        $publicKey = trim($publicKey);
        $vpnIp     = trim($vpnIp);

        if (empty($publicKey) || empty($vpnIp)) {
            return false;
        }

        $interfaceName = config('services.wireguard.interface_name', 'wg0');
        $allowedIps = "{$vpnIp}/32";

        if (PHP_OS_FAMILY === 'Windows') {
            Log::info("[WireGuardService] [Windows Dev] Mock add peer {$publicKey} ({$allowedIps})");
            return true;
        }

        try {
            $cmd = "sudo wg set {$interfaceName} peer " . escapeshellarg($publicKey) . " allowed-ips " . escapeshellarg($allowedIps) . " 2>&1";
            $output = trim((string) shell_exec($cmd));

            self::persistPeerToConfig($interfaceName, $publicKey, $allowedIps);
            return true;
        } catch (\Throwable $e) {
            Log::error("[WireGuardService] Failed to set peer {$publicKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove peer from the VPS WireGuard interface (wg0).
     */
    public static function removePeer(string $publicKey): bool
    {
        $publicKey = trim($publicKey);
        if (empty($publicKey)) {
            return false;
        }

        $interfaceName = config('services.wireguard.interface_name', 'wg0');

        if (PHP_OS_FAMILY === 'Windows') {
            Log::info("[WireGuardService] [Windows Dev] Mock remove peer {$publicKey}");
            return true;
        }

        try {
            $cmd = "sudo wg set {$interfaceName} peer " . escapeshellarg($publicKey) . " remove 2>&1";
            @shell_exec($cmd);

            self::removePeerFromConfig($interfaceName, $publicKey);
            return true;
        } catch (\Throwable $e) {
            Log::error("[WireGuardService] Failed to remove peer {$publicKey}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Write peer to /etc/wireguard/wg0.conf and signal reload.
     */
    private static function persistPeerToConfig(string $iface, string $pubKey, string $allowedIps): void
    {
        $confPath = "/etc/wireguard/{$iface}.conf";
        if (file_exists($confPath) && is_writable($confPath)) {
            $content = (string) file_get_contents($confPath);
            $blocks = explode('[Peer]', $content);
            $header = array_shift($blocks);
            $clean = [];

            foreach ($blocks as $block) {
                if (!str_contains($block, $pubKey) && !str_contains($block, "AllowedIPs = {$allowedIps}")) {
                    $clean[] = '[Peer]' . $block;
                }
            }

            $newBlock = "[Peer]\n# HSmart Managed Router\nPublicKey = {$pubKey}\nAllowedIPs = {$allowedIps}\n";
            $full = rtrim($header) . "\n\n" . implode('', $clean) . $newBlock;
            @file_put_contents($confPath, $full);
        }

        @file_put_contents('/etc/wireguard/.reload_needed', (string) time());
    }

    /**
     * Remove peer from /etc/wireguard/wg0.conf and signal reload.
     */
    private static function removePeerFromConfig(string $iface, string $pubKey): void
    {
        $confPath = "/etc/wireguard/{$iface}.conf";
        if (file_exists($confPath) && is_writable($confPath)) {
            $content = (string) file_get_contents($confPath);
            $blocks = explode('[Peer]', $content);
            $header = array_shift($blocks);
            $clean = [];

            foreach ($blocks as $block) {
                if (!str_contains($block, $pubKey)) {
                    $clean[] = '[Peer]' . $block;
                }
            }

            @file_put_contents($confPath, rtrim($header) . "\n\n" . implode('', $clean));
        }

        @file_put_contents('/etc/wireguard/.reload_needed', (string) time());
    }
}

