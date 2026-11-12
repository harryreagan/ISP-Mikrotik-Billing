<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sms\SmsBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsBalanceController extends Controller
{
    /**
     * Return the active SMS provider balance for the authenticated user's tenant.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;

        if (! $tenantId && $request->hasSession()) {
            $tenantId = $request->session()->get('impersonated_tenant_id')
                ?: $request->session()->get('tenant_id');
        }

        $payload = SmsBalanceService::forTenant($tenantId ? (int) $tenantId : null);

        return response()->json($payload, 200);
    }
}

