<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HospitalitySetting;
use App\Models\HospitalityTicket;
use App\Models\Router;
use App\Services\HospitalityTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HospitalityApiController extends Controller
{
    protected HospitalityTicketService $ticketService;

    public function __construct(HospitalityTicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * Authenticate request using Tenant API Key.
     */
    protected function authenticateSetting(Request $request): ?HospitalitySetting
    {
        $apiKey = $request->header('X-API-Key')
            ?: $request->bearerToken()
            ?: $request->input('api_key');

        if (!$apiKey) {
            return null;
        }

        return HospitalitySetting::withoutGlobalScopes()
            ->where('api_key', trim($apiKey))
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * POST /api/v1/hospitality/ticket
     * Generate a new WiFi Ticket for a Hotel Guest Folio or POS Receipt.
     */
    public function generateTicket(Request $request): JsonResponse
    {
        $setting = $this->authenticateSetting($request);
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing X-API-Key.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'ticket_type'    => 'nullable|string|in:hotel_guest,pos_receipt',
            'source_system'  => 'nullable|string|max:64',
            'guest_name'     => 'nullable|string|max:128',
            'guest_phone'    => 'nullable|string|max:32',
            'room_number'    => 'nullable|string|max:32',
            'nights'         => 'nullable|integer|min:1|max:365',
            'receipt_ref'    => 'nullable|string|max:64',
            'bill_amount'    => 'nullable|numeric|min:0',
            'duration_hours' => 'nullable|integer|min:1|max:8760',
            'device_limit'   => 'nullable|integer|min:1|max:10',
            'package_id'     => 'nullable|integer',
            'router_id'      => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $this->ticketService->generateTicket($request->all(), $setting);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'status'         => 'success',
            'message'        => 'WiFi ticket created successfully.',
            'code'           => $result['code'],
            'ticket_type'    => $result['ticket_type'],
            'duration_hours' => $result['duration_hours'],
            'device_limit'   => $result['device_limit'],
            'expires_at'     => $result['expires_at'],
            'qr_url'         => $result['qr_url'],
            'network_ssid'   => $result['network_ssid'],
            'printable_text' => $result['printable_text'],
            'ticket'         => [
                'id'          => $result['ticket']->id,
                'room_number' => $result['ticket']->room_number,
                'receipt_ref' => $result['ticket']->receipt_ref,
                'guest_name'  => $result['ticket']->guest_name,
                'created_at'  => $result['ticket']->created_at->toDateTimeString(),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/hospitality/verify-guest
     * Verify Room Number & Guest Name/ID from Captive Portal.
     */
    public function verifyGuest(Request $request): JsonResponse
    {
        $setting = $this->authenticateSetting($request);
        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'room_number'   => 'required|string',
            'guest_name_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $res = $this->ticketService->verifyGuestRoom(
            $request->input('room_number'),
            $request->input('guest_name_id'),
            $setting->tenant_id
        );

        if (!$res['valid']) {
            return response()->json(['success' => false, 'message' => $res['message']], 404);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Guest stay verified.',
            'ticket_code'  => $res['ticket_code'],
            'guest_name'   => $res['guest_name'],
            'room_number'  => $res['room_number'],
            'expires_at'   => $res['expires_at'],
            'device_limit' => $res['device_limit'],
        ]);
    }

    /**
     * GET /api/v1/hospitality/tickets
     * List recent tickets.
     */
    public function listTickets(Request $request): JsonResponse
    {
        $setting = $this->authenticateSetting($request);
        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $query = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->latest();

        if ($request->filled('type')) {
            $query->where('ticket_type', $request->input('type'));
        }
        if ($request->filled('room')) {
            $query->where('room_number', $request->input('room'));
        }
        if ($request->filled('receipt')) {
            $query->where('receipt_ref', $request->input('receipt'));
        }

        $tickets = $query->paginate(30);

        return response()->json([
            'success' => true,
            'data'    => $tickets->items(),
            'total'   => $tickets->total(),
            'page'    => $tickets->currentPage(),
        ]);
    }

    /**
     * POST /api/v1/hospitality/ticket/{code}/revoke
     * Revoke an active ticket.
     */
    public function revokeTicket(Request $request, string $code): JsonResponse
    {
        $setting = $this->authenticateSetting($request);
        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $ticket = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $setting->tenant_id)
            ->where('ticket_code', trim($code))
            ->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $ticket->status = 'revoked';
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => "Ticket {$code} has been revoked successfully.",
        ]);
    }

    /**
     * GET /api/v1/hospitality/stats
     * Real-time hospitality analytics for external dashboards (Opera, Cloudbeds, Loyverse).
     */
    public function getStats(Request $request): JsonResponse
    {
        $setting = $this->authenticateSetting($request);
        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $tenantId = $setting->tenant_id;
        $today = now()->startOfDay();

        $activeCount = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $todayCount = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->count();

        $todayHotel = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('ticket_type', 'hotel_guest')
            ->where('created_at', '>=', $today)
            ->count();

        $todayPos = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('ticket_type', 'pos_receipt')
            ->where('created_at', '>=', $today)
            ->count();

        $activeRooms = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('room_number')
            ->where('expires_at', '>', now())
            ->pluck('room_number')
            ->unique()
            ->values();

        $recent = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(10)
            ->get(['id', 'ticket_code', 'ticket_type', 'guest_name', 'room_number', 'receipt_ref', 'status', 'expires_at', 'created_at']);

        return response()->json([
            'success'               => true,
            'business_name'         => $setting->business_name ?: 'Hotel Partner',
            'active_guests_now'     => $activeCount,
            'today_tickets_issued'  => $todayCount,
            'today_hotel_guests'    => $todayHotel,
            'today_pos_receipts'    => $todayPos,
            'active_rooms_online'   => $activeRooms,
            'recent_tickets'        => $recent,
            'rules'                 => [
                'pos_min_spend'        => $setting->pos_min_spend,
                'pos_duration_hours'   => $setting->pos_duration_hours,
                'hotel_duration_hours' => $setting->hotel_duration_hours,
                'hotel_device_limit'   => $setting->hotel_device_limit,
            ]
        ]);
    }
}

