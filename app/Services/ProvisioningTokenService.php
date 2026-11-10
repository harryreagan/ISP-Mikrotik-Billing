<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Str;

class ProvisioningTokenService
{
    /**
     * Issue a secure, expiring provisioning token for a router.
     * Token format: <router_uuid>.<random_secret>
     * Only the SHA-256 hash is stored in the database.
     */
    public static function issueToken(Router $router, int $validMinutes = 30): array
    {
        $rawSecret = Str::random(40);
        $routerUuid = $router->router_uuid ?: (string) Str::uuid();

        $plainToken = "{$routerUuid}.{$rawSecret}";
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = now()->addMinutes($validMinutes);

        $router->updateQuietly([
            'router_uuid'                 => $routerUuid,
            'provisioning_token_hash'     => $tokenHash,
            'provision_token_expires_at'  => $expiresAt,
            'provisioning_status'         => 'waiting_for_router',
        ]);

        return [
            'plain_token' => $plainToken,
            'expires_at'  => $expiresAt,
            'token_hash'  => $tokenHash,
        ];
    }

    /**
     * Validate an incoming provisioning token against the stored hash and expiry.
     */
    public static function validateToken(string $plainToken): ?Router
    {
        if (empty($plainToken) || !str_contains($plainToken, '.')) {
            return null;
        }

        [$routerUuid] = explode('.', $plainToken, 2);
        if (empty($routerUuid)) {
            return null;
        }

        $router = Router::where('router_uuid', $routerUuid)->first();
        if (!$router) {
            return null;
        }

        // Check expiration
        if (!$router->provision_token_expires_at || now()->isAfter($router->provision_token_expires_at)) {
            return null;
        }

        // Verify SHA-256 hash in constant time
        $computedHash = hash('sha256', $plainToken);
        if (!hash_equals((string) $router->provisioning_token_hash, $computedHash)) {
            return null;
        }

        return $router;
    }

    /**
     * Burn token after successful bootstrap and mark router online.
     */
    public static function burnToken(Router $router): void
    {
        $router->updateQuietly([
            'provisioning_token_hash'    => null,
            'provision_token_expires_at' => null,
            'provisioning_status'        => 'completed',
            'status'                     => 'online',
            'provisioned_at'             => now(),
            'last_seen_at'               => now(),
        ]);
    }
}

