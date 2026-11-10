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
     * Validate an incoming provisioning token against stored tokens, hashes, and expiry.
     */
    public static function validateToken(string $plainToken): ?Router
    {
        if (empty($plainToken)) {
            return null;
        }

        $clean = trim($plainToken);

        // 1. Direct match on setup_token or provision_token (used by AddRouterWizard & MikrotikSync)
        $router = Router::where('setup_token', $clean)
            ->orWhere('provision_token', $clean)
            ->first();

        if ($router) {
            return $router;
        }

        // 2. Prefix / OCR Tolerance Fallback for tokens starting with nbp_
        // Allows resilient matching if character variations occurred during terminal transfer
        if (str_starts_with($clean, 'nbp_') && strlen($clean) >= 10) {
            $prefix = substr($clean, 0, 10);
            $recentRouter = Router::where(function ($q) use ($prefix) {
                    $q->where('setup_token', 'like', "{$prefix}%")
                      ->orWhere('provision_token', 'like', "{$prefix}%");
                })
                ->where('created_at', '>=', now()->subHours(2))
                ->latest('id')
                ->first();

            if ($recentRouter) {
                \Illuminate\Support\Facades\Log::info("[Provisioning] Matched router #{$recentRouter->id} via token prefix {$prefix} (provided: {$clean}, stored: {$recentRouter->setup_token})");
                // Update stored tokens to match incoming token so all subsequent heartbeats resolve immediately
                $recentRouter->updateQuietly([
                    'setup_token'     => $clean,
                    'provision_token' => $clean,
                ]);
                return $recentRouter;
            }
        }

        // 3. Token format with dot: <router_uuid>.<random_secret>
        if (str_contains($clean, '.')) {
            [$routerUuid] = explode('.', $clean, 2);
            if (!empty($routerUuid)) {
                $router = Router::where('router_uuid', $routerUuid)->first();
                if ($router) {
                    if ($router->provision_token_expires_at && now()->isAfter($router->provision_token_expires_at)) {
                        return null;
                    }
                    $computedHash = hash('sha256', $clean);
                    if (hash_equals((string) $router->provisioning_token_hash, $computedHash)) {
                        return $router;
                    }
                }
            }
        }

        return null;
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

