<?php

namespace App\Services;

use App\Models\HospitalitySetting;
use App\Models\HospitalityTicket;
use App\Models\HotspotVoucher;
use App\Models\PackageList;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HospitalityTicketService
{
    /**
     * Generate a new Hospitality WiFi Ticket (Hotel Room or POS/Receipt).
     */
    public function generateTicket(array $data, HospitalitySetting $setting): array
    {
        $tenantId = $setting->tenant_id;
        $ticketType = in_array($data['ticket_type'] ?? '', ['hotel_guest', 'pos_receipt'])
            ? $data['ticket_type']
            : (!empty($data['room_number']) ? 'hotel_guest' : 'pos_receipt');

        $billAmount = isset($data['bill_amount']) ? (float) $data['bill_amount'] : null;

        // Check POS Minimum spend threshold
        if ($ticketType === 'pos_receipt' && $billAmount !== null && $billAmount < (float) $setting->pos_min_spend) {
            return [
                'success' => false,
                'message' => "Bill amount of KES {$billAmount} does not meet the minimum requirement of KES {$setting->pos_min_spend} for complimentary WiFi.",
            ];
        }

        // Determine duration in hours
        $durationHours = (int) ($data['duration_hours'] ?? ($ticketType === 'hotel_guest' ? $setting->hotel_duration_hours : $setting->pos_duration_hours));
        if (!empty($data['nights'])) {
            $durationHours = ((int) $data['nights']) * 24;
        }
        $durationHours = max(1, $durationHours);

        // Determine device limit
        $deviceLimit = (int) ($data['device_limit'] ?? ($ticketType === 'hotel_guest' ? $setting->hotel_device_limit : $setting->pos_device_limit));
        $deviceLimit = max(1, $deviceLimit);

        // Determine Package
        $packageId = $data['package_id'] ?? ($ticketType === 'hotel_guest' ? $setting->default_hotel_package_id : $setting->default_pos_package_id);
        $package = $packageId ? PackageList::withoutGlobalScopes()->find($packageId) : null;
        if (!$package) {
            $package = PackageList::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        }

        // Determine Router
        $routerId = $data['router_id'] ?? $setting->selected_router_id;
        $router = $routerId ? Router::withoutGlobalScopes()->find($routerId) : null;
        if (!$router) {
            $router = Router::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        }

        // Generate Unique Voucher Code
        $prefix = $ticketType === 'hotel_guest' ? 'HTL' : 'POS';
        $code = $prefix . '-' . strtoupper(Str::random(6));

        // Format Limit Uptime for MikroTik (e.g., "24h" or "2h")
        $limitUptime = "{$durationHours}h";
        $expiresAt = Carbon::now()->addHours($durationHours);

        // Base Captive Portal URL
        $portalDomain = config('app.url', 'https://isp.hsmart.co.ke');
        $portalDomain = rtrim(str_replace(['http://', 'https://'], '', $portalDomain), '/');
        $qrUrl = "https://{$portalDomain}/portal?code={$code}&auto=1";

        // 1. Create Hospitality Ticket Record
        $ticket = HospitalityTicket::withoutGlobalScopes()->create([
            'tenant_id'      => $tenantId,
            'ticket_code'    => $code,
            'ticket_type'    => $ticketType,
            'source_system'  => $data['source_system'] ?? 'external_api',
            'guest_name'     => $data['guest_name'] ?? null,
            'guest_phone'    => $data['guest_phone'] ?? null,
            'room_number'    => $data['room_number'] ?? null,
            'receipt_ref'    => $data['receipt_ref'] ?? null,
            'bill_amount'    => $billAmount,
            'duration_hours' => $durationHours,
            'device_limit'   => $deviceLimit,
            'package_id'     => $package ? $package->id : null,
            'router_id'      => $router ? $router->id : null,
            'status'         => 'active',
            'expires_at'     => $expiresAt,
            'qr_url'         => $qrUrl,
        ]);

        // 2. Also register into HotspotVoucher pool for consistency
        try {
            HotspotVoucher::withoutGlobalScopes()->create([
                'tenant_id'         => $tenantId,
                'router_name'       => $router ? $router->name : 'default',
                'code'              => $code,
                'username'          => $code,
                'password'          => $code,
                'profile'           => $package ? ($package->mikrotik_profile ?: 'default') : 'default',
                'price'             => 0.00,
                'batch_name'        => 'Hospitality-' . date('Y-m-d'),
                'status'            => 'unused',
                'validity_duration' => $limitUptime,
                'expires_at'        => $expiresAt,
                'comment'           => "{$ticketType} - Room: " . ($data['room_number'] ?? 'N/A') . " Ref: " . ($data['receipt_ref'] ?? 'N/A'),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Hospitality HotspotVoucher creation notice: " . $e->getMessage());
        }

        // 3. Enqueue directly with RouterPendingActivationService for MikroTik synchronization
        try {
            RouterPendingActivationService::enqueue(
                tenantId: $tenantId,
                routerId: $router ? $router->id : null,
                voucherCode: $code,
                username: $code,
                password: $code,
                profile: $package ? $package->mikrotik_profile : null,
                limitUptime: $limitUptime,
                expiresAt: $expiresAt,
                initialError: null
            );
        } catch (\Throwable $e) {
            Log::warning("Hospitality RouterPendingActivationService notice: " . $e->getMessage());
        }

        // 4. Format Printable Slip text (for 58mm / 80mm ESC/POS thermal printers)
        $printableText = $this->formatThermalSlip($ticket, $setting, $router);

        return [
            'success'        => true,
            'ticket_id'      => $ticket->id,
            'code'           => $code,
            'ticket_type'    => $ticketType,
            'duration_hours' => $durationHours,
            'device_limit'   => $deviceLimit,
            'expires_at'     => $expiresAt->toDateTimeString(),
            'qr_url'         => $qrUrl,
            'network_ssid'   => $router ? ($router->hotspot_ssid ?: $router->name) : 'HSmart_Guest_WiFi',
            'printable_text' => $printableText,
            'ticket'         => $ticket,
        ];
    }

    /**
     * Format a clean receipt slip block for thermal receipt printers.
     */
    public function formatThermalSlip(HospitalityTicket $ticket, HospitalitySetting $setting, ?Router $router): string
    {
        $ssid = $router ? ($router->hotspot_ssid ?: $router->name) : 'Guest_WiFi';
        $duration = $ticket->duration_hours >= 24
            ? (int)($ticket->duration_hours / 24) . " Day(s)"
            : "{$ticket->duration_hours} Hour(s)";

        $lines = [];
        $lines[] = "================================";
        $lines[] = "  " . strtoupper($setting->receipt_header ?: 'COMPLIMENTARY GUEST WI-FI');
        $lines[] = "================================";
        $lines[] = "Network (SSID) : " . $ssid;
        $lines[] = "WiFi Code      : " . $ticket->ticket_code;
        $lines[] = "Duration       : " . $duration;
        $lines[] = "Device Limit   : " . $ticket->device_limit . " Device(s)";
        if ($ticket->room_number) {
            $lines[] = "Assigned Room  : Room " . $ticket->room_number;
        }
        if ($ticket->guest_name) {
            $lines[] = "Guest Name     : " . $ticket->guest_name;
        }
        $lines[] = "--------------------------------";
        $lines[] = "   Scan QR code to auto-connect ";
        $lines[] = "  " . $setting->receipt_footer;
        $lines[] = "================================";

        return implode("\n", $lines);
    }

    /**
     * Verify a hotel room guest login attempt from captive portal.
     */
    public function verifyGuestRoom(string $roomNumber, string $guestIdentifier, int $tenantId): array
    {
        $cleanRoom = trim($roomNumber);
        $cleanIdent = strtolower(trim($guestIdentifier));

        $ticket = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('room_number', $cleanRoom)
            ->where('status', 'active')
            ->where(function ($q) use ($cleanIdent) {
                $q->whereRaw('LOWER(guest_name) LIKE ?', ["%{$cleanIdent}%"])
                  ->orWhere('guest_phone', 'LIKE', "%{$cleanIdent}%")
                  ->orWhere('receipt_ref', 'LIKE', "%{$cleanIdent}%");
            })
            ->latest()
            ->first();

        if (!$ticket) {
            return [
                'valid'   => false,
                'message' => "No active room stay found for Room {$roomNumber} matching the guest credentials.",
            ];
        }

        return [
            'valid'        => true,
            'ticket_code'  => $ticket->ticket_code,
            'guest_name'   => $ticket->guest_name,
            'room_number'  => $ticket->room_number,
            'expires_at'   => $ticket->expires_at?->toDateTimeString(),
            'device_limit' => $ticket->device_limit,
        ];
    }
}
