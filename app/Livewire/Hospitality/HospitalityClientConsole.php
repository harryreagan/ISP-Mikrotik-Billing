<?php

namespace App\Livewire\Hospitality;

use App\Models\HospitalitySetting;
use App\Models\HospitalityTicket;
use App\Models\Router;
use App\Services\HospitalityTicketService;
use Livewire\Component;
use Livewire\WithPagination;

class HospitalityClientConsole extends Component
{
    use WithPagination;

    public string $token = '';
    public int $tenantId = 1;
    public string $businessName = 'Hotel Partner';
    public ?HospitalitySetting $setting = null;

    // Issue Ticket Modal Form
    public string $ticket_type = 'hotel_guest';
    public string $guest_name = '';
    public string $guest_phone = '';
    public string $room_number = '';
    public int $nights = 1;
    public float $bill_amount = 0.00;
    public string $receipt_ref = '';
    public int $device_limit = 3;

    // Latest Generated Ticket for On-Screen Modal / Printing
    public ?array $activeTicket = null;
    public bool $showSuccessModal = false;

    // Search and filters
    public string $search = '';
    public string $typeFilter = 'all';

    protected $paginationTheme = 'bootstrap';

    public function mount(string $token)
    {
        $this->token = $token;
        $setting = HospitalitySetting::withoutGlobalScopes()
            ->where('portal_token', $token)
            ->first();

        if (!$setting || !$setting->is_enabled) {
            abort(403, 'This hospitality portal link is invalid, expired, or disabled. Contact your network administrator.');
        }

        $this->tenantId = $setting->tenant_id;
        $this->businessName = $setting->business_name ?: 'Hotel Partner';
        $this->device_limit = $setting->hotel_device_limit ?: 3;
    }

    public function issueTicket(HospitalityTicketService $ticketService)
    {
        $setting = HospitalitySetting::withoutGlobalScopes()->where('portal_token', $this->token)->firstOrFail();

        $payload = [
            'ticket_type'   => $this->ticket_type,
            'source_system' => 'hospitality_staff_portal',
            'guest_name'    => trim($this->guest_name) ?: 'Guest',
            'guest_phone'   => trim($this->guest_phone),
            'room_number'   => $this->ticket_type === 'hotel_guest' ? trim($this->room_number) : null,
            'nights'        => $this->ticket_type === 'hotel_guest' ? max(1, $this->nights) : null,
            'bill_amount'   => $this->ticket_type === 'pos_receipt' ? $this->bill_amount : null,
            'receipt_ref'   => trim($this->receipt_ref) ?: ($this->ticket_type === 'pos_receipt' ? 'POS-' . rand(1000, 9999) : null),
            'device_limit'  => $this->device_limit,
            'router_id'     => $setting->selected_router_id,
        ];

        $res = $ticketService->generateTicket($payload, $setting);

        if ($res['success']) {
            $this->activeTicket = $res;
            $this->showSuccessModal = true;
            $this->reset(['guest_name', 'guest_phone', 'room_number', 'receipt_ref', 'bill_amount']);
            $this->resetPage();
            session()->flash('success', "WiFi Ticket {$res['code']} generated successfully!");
        } else {
            session()->flash('error', $res['message']);
        }
    }

    public function reprintTicket(int $ticketId, HospitalityTicketService $ticketService)
    {
        $setting = HospitalitySetting::withoutGlobalScopes()->where('portal_token', $this->token)->firstOrFail();
        $ticket = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->find($ticketId);

        if (!$ticket) {
            return;
        }

        $router = $ticket->router_id ? Router::withoutGlobalScopes()->find($ticket->router_id) : null;
        $printable = $ticketService->formatThermalSlip($ticket, $setting, $router);

        $this->activeTicket = [
            'code'           => $ticket->ticket_code,
            'ticket_type'    => $ticket->ticket_type,
            'duration_hours' => $ticket->duration_hours,
            'device_limit'   => $ticket->device_limit,
            'expires_at'     => $ticket->expires_at?->toDateTimeString(),
            'qr_url'         => $ticket->qr_url,
            'network_ssid'   => $router ? ($router->hotspot_ssid ?: $router->name) : 'Guest_WiFi',
            'printable_text' => $printable,
            'ticket'         => $ticket,
        ];

        $this->showSuccessModal = true;
    }

    public function revokeTicket(int $ticketId)
    {
        $ticket = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->find($ticketId);

        if ($ticket) {
            $ticket->update(['status' => 'revoked']);
            session()->flash('success', "Ticket {$ticket->ticket_code} has been revoked.");
        }
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->activeTicket = null;
    }

    public function render()
    {
        $today = now()->startOfDay();

        $activeCount = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $todayCount = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', $today)
            ->count();

        $occupiedRooms = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->whereNotNull('room_number')
            ->where('expires_at', '>', now())
            ->distinct('room_number')
            ->count('room_number');

        $query = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->latest();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('ticket_code', 'like', "%{$this->search}%")
                  ->orWhere('guest_name', 'like', "%{$this->search}%")
                  ->orWhere('room_number', 'like', "%{$this->search}%")
                  ->orWhere('receipt_ref', 'like', "%{$this->search}%");
            });
        }

        if ($this->typeFilter !== 'all') {
            $query->where('ticket_type', $this->typeFilter);
        }

        $tickets = $query->paginate(20);

        return view('livewire.hospitality.hospitality-client-console', [
            'activeCount'   => $activeCount,
            'todayCount'    => $todayCount,
            'occupiedRooms' => $occupiedRooms,
            'tickets'       => $tickets,
        ])->layout('layouts.hospitality-client', [
            'title' => $this->businessName . ' - WiFi Reception Console'
        ]);
    }
}
