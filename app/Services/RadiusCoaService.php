<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RadiusCoaService
{
    /**
     * Send an RFC 3576 Disconnect-Request to terminate a user's session on the MikroTik router.
     *
     * @param string $username Hotspot or PPPoE username
     * @param string|null $routerIp Router IP address (defaults to router resolving by user's active session)
     * @param string|null $secret Shared secret (defaults to router secret or radius secret)
     * @param int $port Defaults to standard RFC 3576 port 3799
     * @return array ['success' => bool, 'message' => string]
     */
    public static function disconnectUser(string $username, ?string $routerIp = null, ?string $secret = null, int $port = 3799): array
    {
        $username = trim($username);
        if (empty($username)) {
            return ['success' => false, 'message' => 'Username is required.'];
        }

        // If router IP is not specified, resolve from latest active session in radacct
        if (empty($routerIp)) {
            $activeSession = DB::table('radacct')
                ->where('username', $username)
                ->whereNull('acctstoptime')
                ->latest('acctstarttime')
                ->first();

            if ($activeSession && !empty($activeSession->nasipaddress)) {
                $routerIp = $activeSession->nasipaddress;
            }
        }

        if (empty($routerIp)) {
            // Check if user is associated with a specific router
            $router = Router::where('status', 'online')->whereNotNull('vpn_ip')->first();
            $routerIp = $router?->vpn_ip;
        }

        if (empty($routerIp)) {
            return ['success' => false, 'message' => 'No router IP found for active session.'];
        }

        if (empty($secret)) {
            $nasRow = DB::table('nas')->where('nasname', $routerIp)->first();
            $secret = $nasRow?->secret ?: config('services.radius.secret', 'hsmart_rad_secret');
        }

        // 1. Try radclient CLI tool if available
        $hasRadclient = !empty(trim((string) @shell_exec('which radclient 2>/dev/null')));
        if ($hasRadclient) {
            try {
                $input = "User-Name = \"{$username}\"";
                $cmd = "echo " . escapeshellarg($input) . " | radclient -r 2 -t 2 " . escapeshellarg("{$routerIp}:{$port}") . " disconnect " . escapeshellarg($secret) . " 2>&1";
                $output = trim((string) shell_exec($cmd));

                if (str_contains($output, 'Received Disconnect-ACK') || str_contains($output, 'ACK')) {
                    Log::info("[RadiusCoaService] Disconnect-ACK received for user '{$username}' on {$routerIp}");
                    return ['success' => true, 'message' => 'User disconnected successfully via radclient.'];
                }
            } catch (\Throwable $e) {
                Log::warning("[RadiusCoaService] radclient command failed: " . $e->getMessage());
            }
        }

        // 2. Pure PHP UDP socket implementation of RFC 3576 Disconnect-Request
        try {
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (!$sock) {
                return ['success' => false, 'message' => 'Failed to create UDP socket.'];
            }

            socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);

            $packet = self::buildDisconnectPacket($username, $secret);
            socket_sendto($sock, $packet, strlen($packet), 0, $routerIp, $port);

            $buf = '';
            $from = '';
            $fromPort = 0;
            $received = @socket_recvfrom($sock, $buf, 1024, 0, $from, $fromPort);
            socket_close($sock);

            if ($received !== false && strlen($buf) >= 20) {
                $code = ord($buf[0]);
                // Code 41 = Disconnect-ACK, Code 42 = Disconnect-NAK
                if ($code === 41) {
                    Log::info("[RadiusCoaService] Socket Disconnect-ACK received for '{$username}' on {$routerIp}");
                    return ['success' => true, 'message' => 'Disconnect-ACK received from MikroTik.'];
                } elseif ($code === 42) {
                    return ['success' => false, 'message' => 'Disconnect-NAK received (session already ended or invalid).'];
                }
            }

            Log::info("[RadiusCoaService] Disconnect-Request dispatched to {$routerIp}:{$port} for '{$username}'");
            return ['success' => true, 'message' => 'Disconnect-Request dispatched.'];
        } catch (\Throwable $e) {
            Log::error("[RadiusCoaService] Socket error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Socket exception: ' . $e->getMessage()];
        }
    }

    /**
     * Build an RFC 3576 Disconnect-Request packet with MD5 authenticator.
     */
    private static function buildDisconnectPacket(string $username, string $secret): string
    {
        $code = 40; // Disconnect-Request
        $identifier = mt_rand(1, 255);
        $authenticator = random_bytes(16);

        // Attribute 1: User-Name
        $attrType = 1;
        $attrVal = $username;
        $attrLen = strlen($attrVal) + 2;
        $attributes = chr($attrType) . chr($attrLen) . $attrVal;

        $length = 20 + strlen($attributes);

        // Packet header before MD5 hash
        $header = chr($code) . chr($identifier) . pack('n', $length) . $authenticator;

        // Calculate Response Authenticator: MD5(Code + ID + Length + RequestAuth + Attributes + Secret)
        $hash = md5($header . $attributes . $secret, true);

        // Disconnect-Request replaces authenticator with MD5 of (Code + ID + Length + 16x00 + Attributes + Secret)
        $zeroAuth = str_repeat("\x00", 16);
        $reqHash = md5(chr($code) . chr($identifier) . pack('n', $length) . $zeroAuth . $attributes . $secret, true);

        return chr($code) . chr($identifier) . pack('n', $length) . $reqHash . $attributes;
    }
}

