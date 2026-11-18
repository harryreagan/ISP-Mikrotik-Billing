<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IspLiveChatController extends Controller
{
    protected function getPosBaseUrl(): string
    {
        return config('hub.pos_internal_url')
            ?: env('POS_INTERNAL_URL', 'https://pos.hsmart.co.ke');
    }

    /**
     * Resolve effective tenant ID and tenant name with multi-tenant isolation.
     */
    protected function resolveTenantInfo(?string $requestedTenantId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user && (
            in_array(strtolower($user->role ?? ''), ['admin', 'superadmin', 'super_admin'])
            || ($user->tenant_id === null || $user->tenant_id == 1)
        );

        if ($isSuperAdmin && !empty($requestedTenantId)) {
            $tenantId = (string) $requestedTenantId;
        } else {
            $tenantId = (string) (TenantContext::getTenantId() ?: ($user?->tenant_id ?: (session('tenant_id') ?: 1)));
        }

        $tenant = Tenant::find($tenantId);
        $tenantName = $tenant?->name ?: ($user?->tenant?->name ?: ($user?->name ?: 'ISP'));

        return [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'is_super_admin' => $isSuperAdmin,
        ];
    }

    /**
     * Get chat messages
     */
    public function messages(Request $request): JsonResponse
    {
        $tenantInfo = $this->resolveTenantInfo($request->input('tenant_id'));

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(6)
                ->get($this->getPosBaseUrl() . '/api/isp-chat/messages', [
                    'tenant_id' => $tenantInfo['tenant_id'],
                    'tenant_name' => $tenantInfo['tenant_name'],
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Throwable $e) {
            Log::warning("[IspLiveChat] Messages fetch notice: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'messages' => [],
            'is_admin_online' => true,
        ]);
    }

    /**
     * Send live chat message to Super Admin Harry
     */
    public function send(Request $request): JsonResponse
    {
        $message = $request->input('message')
            ?? $request->json('message')
            ?? ($request->getContent() ? (json_decode($request->getContent(), true)['message'] ?? null) : null);

        if (empty(trim((string)$message))) {
            return response()->json(['success' => false, 'message' => 'Message cannot be empty.'], 422);
        }

        $tenantInfo = $this->resolveTenantInfo($request->input('tenant_id'));
        $senderName = $tenantInfo['is_super_admin'] ? 'HSmart HQ Support' : (auth()->user()?->name ?: 'ISP Admin');

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(8)
                ->post($this->getPosBaseUrl() . '/api/isp-chat/send', [
                    'message' => trim((string)$message),
                    'tenant_id' => $tenantInfo['tenant_id'],
                    'tenant_name' => $tenantInfo['tenant_name'],
                    'sender_name' => $senderName,
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Throwable $e) {
            Log::error("[IspLiveChat] Send message error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not deliver message to Super Admin. Please WhatsApp Harry directly.',
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Platform live chat service temporarily unreachable.',
        ], 502);
    }

    /**
     * Unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $tenantInfo = $this->resolveTenantInfo($request->input('tenant_id'));

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(4)
                ->get($this->getPosBaseUrl() . '/api/isp-chat/unread-count', [
                    'tenant_id' => $tenantInfo['tenant_id'],
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'unread_count' => 0,
            'is_admin_online' => true,
        ]);
    }
}
