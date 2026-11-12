@php
    $smsSettingsUrl = Route::has('admin.sms-gateways') ? route('admin.sms-gateways') : url('/admin/sms-email?tab=gateways');
@endphp

<div
    wire:ignore
    x-data="{
        status: 'none',
        provider: null,
        balanceFormatted: null,
        loaded: false,
        timer: null,
        settingsUrl: @js($smsSettingsUrl),
        init() {
            this.fetchBalance();
            this.timer = setInterval(() => this.fetchBalance(), 60000);
        },
        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        async fetchBalance() {
            try {
                const res = await fetch('/api/sms/balance', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this.status = data.status || 'none';
                this.provider = data.provider || null;
                this.balanceFormatted = data.balance_formatted || null;
                this.loaded = true;
            } catch (e) {
                this.status = 'error';
                this.balanceFormatted = null;
                this.loaded = true;
            }
        },
        get tooltipText() {
            if (this.status === 'none') {
                return this.provider
                    ? 'SMS balance · ' + this.provider
                    : 'No SMS provider activated · Click to configure SMS settings';
            }
            return 'SMS balance · ' + (this.provider || 'SMS Gateway');
        },
        get displayText() {
            if (!this.loaded || this.status === 'error') return '—';
            if (this.status === 'none') return 'No SMS';
            return this.balanceFormatted || '—';
        }
    }"
    x-init="init()"
    class="d-inline-flex align-items-center flex-shrink-0"
>
    <a
        :href="status === 'none' ? settingsUrl : 'javascript:void(0)'"
        role="status"
        :title="tooltipText"
        class="sms-balance-chip"
        :class="{
            'sms-chip-ok': loaded && status === 'ok',
            'sms-chip-low': loaded && status === 'low',
            'sms-chip-empty': loaded && status === 'empty',
            'sms-chip-neutral': !loaded || status === 'none' || status === 'error',
            'sms-chip-clickable': status === 'none'
        }"
    >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="flex-shrink-0">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span x-show="status !== 'none'" class="d-none d-sm-inline">SMS</span>
        <span class="sms-chip-val" x-text="displayText">—</span>
    </a>
</div>

