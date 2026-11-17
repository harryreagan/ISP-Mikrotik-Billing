<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProvisioningTokenService;
use App\Services\RadiusService;
use App\Services\RouterScriptGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ProvisioningController extends Controller
{
    /**
     * Return text/plain .rsc bootstrap script for a valid provisioning token.
     * GET /provision/{token}/bootstrap
     */
    public function bootstrap(Request $request, string $token): Response
    {
        $router = ProvisioningTokenService::validateToken($token);

        if (!$router) {
            return response(
                "# Error: Invalid, expired, or already consumed provisioning token.\r\n:log error \"HSmart: Provisioning token invalid or expired.\"",
                401,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }

        $baseUrl = config('app.url', $request->getSchemeAndHttpHost());
        $script = RouterScriptGeneratorService::generateScript($router, $token, $baseUrl);

        return response($script, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="bootstrap.rsc"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Completion callback endpoint: burn token, register in NAS, mark router online.
     * GET /provision/{token}/done
     */
    public function done(Request $request, string $token): Response
    {
        $router = ProvisioningTokenService::validateToken($token);

        if (!$router) {
            return response("Error: Token invalid or expired.", 401, ['Content-Type' => 'text/plain']);
        }

        // Register router into FreeRADIUS nas table
        RadiusService::registerNas($router);

        // Burn the single-use token and mark router online
        ProvisioningTokenService::burnToken($router);

        // Immediate background telemetry sync & captive portal push so hardware details and hotspot portal are configured immediately
        try {
            (new \App\Livewire\Admin\RoutersManager())->pollRouterTelemetry($router->id, true);
        } catch (\Throwable $te) {
            Log::debug("Provisioning immediate telemetry poll failed: " . $te->getMessage());
        }

        try {
            \App\Services\CaptivePortalTemplates::pushAllToRouter($router->fresh());
        } catch (\Throwable $pe) {
            Log::debug("Provisioning automatic captive portal push note: " . $pe->getMessage());
        }

        Log::info("[Provisioning] Router ID {$router->id} ('{$router->name}') marked ONLINE via bootstrap callback.");

        return response("OK - Router {$router->name} provisioned successfully and is now ONLINE.\r\n", 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}

