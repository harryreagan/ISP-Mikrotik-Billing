@php
    $smsSettingsUrl = Route::has('admin.sms-gateways') ? route('admin.sms-gateways') : url('/admin/sms-email?tab=gateways');
@endphp

<div
    wire:ignore
    x-data="{
        status: 'ok',
        provider: null,
        balanceFormatted: '0 SMS',
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
                this.status = data.status || 'ok';
                this.provider = data.provider || 'System SMS';
                this.balanceFormatted = data.balance_formatted || ((data.balance !== null && data.balance !== undefined) ? parseInt(data.balance) + ' SMS' : '0 SMS');
                this.loaded = true;
            } catch (e) {
                this.status = 'empty';
                this.balanceFormatted = '0 SMS';
                this.loaded = true;
            }
        },
        get tooltipText() {
            return (this.provider || 'SMS Gateway') + ' · ' + this.balanceFormatted;
        }
    }"
    x-init="init()"
    class="d-inline-flex align-items-center flex-shrink-0"
>
    <a
        :href="settingsUrl"
        role="status"
        :title="tooltipText"
        class="sms-balance-pill text-decoration-none"
        :class="{
            'pill-empty': loaded && (status === 'empty' || status === 'none' || balanceFormatted === '0 SMS'),
            'pill-low': loaded && status === 'low',
            'pill-ok': loaded && status === 'ok' && balanceFormatted !== '0 SMS'
        }"
    >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span class="sms-val font-semibold" x-text="balanceFormatted">0 SMS</span>
    </a>
</div>
