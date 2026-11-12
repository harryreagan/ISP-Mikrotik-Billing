<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    settingsHref: {
        type: String,
        default: '/admin/sms-gateways',
    },
});

const status = ref('none');
const provider = ref(null);
const balanceFormatted = ref(null);
const loaded = ref(false);
let pollTimer = null;

async function fetchBalance() {
    try {
        const res = await fetch('/api/sms/balance', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const data = await res.json();
        status.value = data.status || 'none';
        provider.value = data.provider || null;
        balanceFormatted.value = data.balance_formatted || null;
        loaded.value = true;
    } catch {
        // Silent failure: neutral "SMS —" chip, never an error popup
        status.value = 'error';
        balanceFormatted.value = null;
        loaded.value = true;
    }
}

onMounted(() => {
    fetchBalance();
    pollTimer = setInterval(fetchBalance, 60000);
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
});

const chipClasses = computed(() => {
    switch (status.value) {
        case 'ok':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'low':
            return 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300';
        case 'empty':
            return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300';
        default:
            return 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
});

const tooltipText = computed(() => {
    if (status.value === 'none') {
        return provider.value
            ? `SMS balance · ${provider.value} (configure or check credentials)`
            : 'No SMS provider activated · Click to configure SMS settings';
    }
    return `SMS balance · ${provider.value || 'SMS Gateway'}`;
});

const displayText = computed(() => {
    if (!loaded.value || status.value === 'error') {
        return '—';
    }
    if (status.value === 'none') {
        return 'No SMS';
    }
    return balanceFormatted.value || '—';
});
</script>

<template>
    <component
        :is="status === 'none' && settingsHref ? 'a' : 'div'"
        :href="status === 'none' && settingsHref ? settingsHref : undefined"
        role="status"
        :title="tooltipText"
        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors"
        :class="chipClasses"
    >
        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <span v-if="status !== 'none'" class="hidden sm:inline">SMS</span>
        <span class="tabular-nums">{{ displayText }}</span>
    </component>
</template>

