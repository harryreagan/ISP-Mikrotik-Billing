<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IspLiveChatController extends Controller
{
    protected function getPosBaseUrl(): string
    {
        return config('hub.pos_internal_url', 'http://127.0.0.1:8080');
    }

    /**
     * Get chat messages
     */
    public function messages(Request $request): JsonResponse
    {
        $tenantId = (string) (auth()->user()?->tenant_id ?: (session('tenant_id') ?: 1));
        $tenantName = auth()->user()?->tenant?->name ?: (auth()->user()?->name ?: 'ISP');

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(5)
                ->get($this->getPosBaseUrl() . '/api/isp-chat/messages', [
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenantName,
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

        $tenantId = (string) (auth()->user()?->tenant_id ?: (session('tenant_id') ?: 1));
        $tenantName = auth()->user()?->tenant?->name ?: (auth()->user()?->name ?: 'ISP');
        $senderName = auth()->user()?->name ?: 'ISP Admin';

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(8)
                ->post($this->getPosBaseUrl() . '/api/isp-chat/send', [
                    'message' => trim((string)$message),
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenantName,
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
        $tenantId = (string) (auth()->user()?->tenant_id ?: (session('tenant_id') ?: 1));

        try {
            $response = Http::withHeaders(['Host' => 'pos.hsmart.co.ke'])
                ->timeout(3)
                ->get($this->getPosBaseUrl() . '/api/isp-chat/unread-count', [
                    'tenant_id' => $tenantId,
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
