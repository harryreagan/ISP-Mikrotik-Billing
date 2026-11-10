<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RadiusService
{
    /**
     * Sync router into FreeRADIUS `nas` table.
     */
    public static function registerNas(Router $router): void
    {
        $vpnIp = $router->vpn_ip;
        if (empty($vpnIp)) {
            return;
        }

        $secret = $router->radius_secret ?: config('services.radius.secret', 'hsmart_rad_secret');

        DB::table('nas')->updateOrInsert(
            ['nasname' => $vpnIp],
            [
                'shortname'   => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $router->name ?: 'router'), 0, 32),
                'type'        => 'mikrotik',
                'ports'       => 1812,
                'secret'      => $secret,
                'description' => "HSmart Managed Router #{$router->id} ({$router->name})",
            ]
        );
    }

    /**
     * Remove router from FreeRADIUS `nas` table.
     */
    public static function unregisterNas(Router $router): void
    {
        if (!empty($router->vpn_ip)) {
            DB::table('nas')->where('nasname', $router->vpn_ip)->delete();
        }
    }

    /**
     * Create or update a subscriber/voucher in FreeRADIUS (`radcheck` and `radreply`).
     *
     * @param string $username Voucher code or PPPoE username
     * @param string $password Voucher code or PPPoE password
     * @param string|null $rateLimit e.g. "5M/5M" or "10M/10M"
     * @param \Carbon\Carbon|string|null $expiresAt Expiration date/time
     * @param int $simultaneousUse Defaults to 1 concurrent session
     */
    public static function upsertUser(
        string $username,
        string $password,
        ?string $rateLimit = null,
        $expiresAt = null,
        int $simultaneousUse = 1
    ): void {
        $username = trim($username);
        $password = trim($password);

        if (empty($username)) {
            return;
        }

        DB::transaction(function () use ($username, $password, $rateLimit, $expiresAt, $simultaneousUse) {
            // 1. radcheck: Password & Simultaneous-Use
            DB::table('radcheck')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $password]
            );

            if ($simultaneousUse > 0) {
                DB::table('radcheck')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Simultaneous-Use'],
                    ['op' => ':=', 'value' => (string) $simultaneousUse]
                );
            }

            // Expiration attribute in radcheck (Format: "DD Mon YYYY HH:MM:SS" e.g. "10 Nov 2026 14:30:00")
            if ($expiresAt) {
                $carbonExp = is_string($expiresAt) ? \Carbon\Carbon::parse($expiresAt) : $expiresAt;
                $radExpFormat = $carbonExp->format('d M Y H:i:s');

                DB::table('radcheck')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Expiration'],
                    ['op' => ':=', 'value' => $radExpFormat]
                );
            }

            // 2. radreply: Mikrotik-Rate-Limit (Upload/Download e.g. "5M/10M")
            if (!empty($rateLimit)) {
                // Ensure format e.g. "5M/5M"
                $formattedRate = str_contains($rateLimit, '/') ? $rateLimit : "{$rateLimit}/{$rateLimit}";

                DB::table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => '=', 'value' => $formattedRate]
                );
            }
        });

        Log::info("[RadiusService] Upserted RADIUS credentials for user '{$username}' (Rate: {$rateLimit})");
    }

    /**
     * Remove a user completely from FreeRADIUS.
     */
    public static function deleteUser(string $username): void
    {
        $username = trim($username);
        if (empty($username)) {
            return;
        }

        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();
        DB::table('radusergroup')->where('username', $username)->delete();

        Log::info("[RadiusService] Removed RADIUS user '{$username}'");
    }

    /**
     * Check if a user is currently active and authenticated.
     */
    public static function isUserOnline(string $username): bool
    {
        return DB::table('radacct')
            ->where('username', $username)
            ->whereNull('acctstoptime')
            ->exists();
    }
}

