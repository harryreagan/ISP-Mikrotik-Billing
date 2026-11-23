<?php

namespace App\Livewire\Admin;

use App\Models\HospitalitySetting;
use App\Models\HospitalityTicket;
use App\Models\PackageList;
use App\Models\Router;
use App\Services\HospitalityTicketService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class HospitalityPosManager extends Component
{
    use WithPagination;

    // Settings fields
    public bool $is_enabled = true;
    public ?int $default_hotel_package_id = null;
    public int $hotel_duration_hours = 24;
    public int $hotel_device_limit = 3;
    public bool $allow_room_guest_login = true;

    public ?int $default_pos_package_id = null;
    public float $pos_min_spend = 100.00;
    public int $pos_duration_hours = 2;
    public int $pos_device_limit = 1;

    public string $receipt_header = 'COMPLIMENTARY GUEST WI-FI';
    public string $receipt_footer = 'Scan QR code with phone camera to connect';
    public ?int $selected_router_id = null;

    public string $api_key = '';
    public string $webhook_secret = '';

    // Quick Test Ticket Generator
    public string $test_type = 'pos_receipt';
    public string $test_guest_name = 'Jane Doe';
    public string $test_room_number = '204';
    public string $test_receipt_ref = 'REC-1002';
    public float $test_bill_amount = 350.00;
    public int $test_duration_hours = 2;
    public int $test_device_limit = 1;
    public ?array $generatedTicket = null;

    // Table filters
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';

    public string $activeDocTab = 'curl';

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $setting = HospitalitySetting::forTenant($tenantId);

        $this->is_enabled = (bool) $setting->is_enabled;
        $this->default_hotel_package_id = $setting->default_hotel_package_id;
        $this->hotel_duration_hours = (int) $setting->hotel_duration_hours;
        $this->hotel_device_limit = (int) $setting->hotel_device_limit;
        $this->allow_room_guest_login = (bool) $setting->allow_room_guest_login;

        $this->default_pos_package_id = $setting->default_pos_package_id;
        $this->pos_min_spend = (float) $setting->pos_min_spend;
        $this->pos_duration_hours = (int) $setting->pos_duration_hours;
        $this->pos_device_limit = (int) $setting->pos_device_limit;

        $this->receipt_header = $setting->receipt_header ?: 'COMPLIMENTARY GUEST WI-FI';
        $this->receipt_footer = $setting->receipt_footer ?: 'Scan QR code with phone camera to connect';
        $this->selected_router_id = $setting->selected_router_id;

        $this->api_key = $setting->api_key;
        $this->webhook_secret = $setting->webhook_secret ?: 'whsec_' . Str::random(24);
    }

    public function saveSettings()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $setting = HospitalitySetting::forTenant($tenantId);

        $setting->update([
            'is_enabled'               => $this->is_enabled,
            'default_hotel_package_id' => $this->default_hotel_package_id ?: null,
            'hotel_duration_hours'     => max(1, $this->hotel_duration_hours),
            'hotel_device_limit'       => max(1, $this->hotel_device_limit),
            'allow_room_guest_login'   => $this->allow_room_guest_login,
            'default_pos_package_id'   => $this->default_pos_package_id ?: null,
            'pos_min_spend'            => max(0, $this->pos_min_spend),
            'pos_duration_hours'       => max(1, $this->pos_duration_hours),
            'pos_device_limit'         => max(1, $this->pos_device_limit),
            'receipt_header'           => trim($this->receipt_header),
            'receipt_footer'           => trim($this->receipt_footer),
            'selected_router_id'       => $this->selected_router_id ?: null,
        ]);

        session()->flash('success', 'Hospitality & POS WiFi integration settings updated successfully!');
    }

    public function regenerateApiKey()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $setting = HospitalitySetting::forTenant($tenantId);

        $newKey = 'hsm_live_' . Str::random(32);
        $setting->update(['api_key' => $newKey]);
        $this->api_key = $newKey;

        session()->flash('success', 'API Key regenerated! Make sure to update your external POS or Hotel PMS integration.');
    }

    public function issueTestTicket(HospitalityTicketService $ticketService)
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $setting = HospitalitySetting::forTenant($tenantId);

        $payload = [
            'ticket_type'    => $this->test_type,
            'source_system'  => 'admin_test_terminal',
            'guest_name'     => $this->test_guest_name,
            'room_number'    => $this->test_type === 'hotel_guest' ? $this->test_room_number : null,
            'receipt_ref'    => $this->test_receipt_ref,
            'bill_amount'    => $this->test_bill_amount,
            'duration_hours' => $this->test_type === 'hotel_guest' ? $this->hotel_duration_hours : $this->test_duration_hours,
            'device_limit'   => $this->test_type === 'hotel_guest' ? $this->hotel_device_limit : $this->test_device_limit,
            'router_id'      => $this->selected_router_id,
        ];

        $res = $ticketService->generateTicket($payload, $setting);

        if ($res['success']) {
            $this->generatedTicket = $res;
            $this->resetPage();
            session()->flash('ticket_success', "WiFi Ticket {$res['code']} issued successfully!");
        } else {
            session()->flash('ticket_error', $res['message']);
        }
    }

    public function revokeTicket(int $ticketId)
    {
        $tenantId = auth()->user()->tenant_id ?? 1;
        $ticket = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if ($ticket) {
            $ticket->update(['status' => 'revoked']);
            session()->flash('success', "Ticket {$ticket->ticket_code} has been revoked.");
        }
    }

    public function render()
    {
        $tenantId = auth()->user()->tenant_id ?? 1;

        $packages = PackageList::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $routers = Router::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $query = HospitalityTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
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

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        $tickets = $query->paginate(15);

        return view('livewire.admin.hospitality-pos-manager', [
            'packages' => $packages,
            'routers'  => $routers,
            'tickets'  => $tickets,
        ])->layout('layouts.app');
    }
}
