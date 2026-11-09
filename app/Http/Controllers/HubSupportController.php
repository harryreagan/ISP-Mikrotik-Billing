<?php

namespace App\Http\Controllers;

use App\Services\HubConnector\HubClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubSupportController extends Controller
{
    /**
     * Submit ticket to HSmart Platforms Super Admin Hub
     */
    public function submitTicket(Request $request, HubClient $client): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'message' => 'required|string',
        ]);

        $response = $client->send('POST', '/api/hub/v1/tickets', [
            'subject' => $validated['subject'],
            'priority' => $validated['priority'] ?? 'medium',
            'message' => $validated['message'],
            'sender_name' => auth()->user()?->name ?? 'ISP Administrator',
        ]);

        if ($response['success'] ?? false) {
            return response()->json([
                'success' => true,
                'message' => 'Support request submitted directly to HSmart Platforms Super Admin.',
                'ticket_id' => $response['ticket_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to reach platform support: ' . ($response['error'] ?? 'Connection error'),
        ], 500);
    }
}

