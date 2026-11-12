/**
 * HSmart ISP Landing Page Configuration (Single Source of Truth)
 * Replace WHATSAPP_NUMBER with your production WhatsApp number (E.164 digits, no +).
 */
export const BRAND_NAME = 'HSmart ISP';
export const WHATSAPP_NUMBER = '254743606108'; // Placeholder: [YOUR NUMBER]
export const WHATSAPP_DISPLAY_PLACEHOLDER = '[YOUR NUMBER]';
export const SUPPORT_EMAIL = 'support@hsmart.co.ke';
export const DEFAULT_CURRENCY = 'USD';

export const CURRENCIES = {
    USD: { code: 'USD', symbol: '$',    rateFromKes: 0.0078, decimals: 0 },
    KES: { code: 'KES', symbol: 'KES ', rateFromKes: 1.0,    decimals: 0 },
    NGN: { code: 'NGN', symbol: 'NGN ', rateFromKes: 12.5,   decimals: 0 },
    GHS: { code: 'GHS', symbol: 'GHS ', rateFromKes: 0.12,   decimals: 0 },
    TZS: { code: 'TZS', symbol: 'TZS ', rateFromKes: 20.5,   decimals: 0 },
    UGX: { code: 'UGX', symbol: 'UGX ', rateFromKes: 28.5,   decimals: 0 },
    ZAR: { code: 'ZAR', symbol: 'R ',   rateFromKes: 0.14,   decimals: 0 },
    EUR: { code: 'EUR', symbol: '€',    rateFromKes: 0.0072, decimals: 0 },
};

// Base plan prices preserved from existing plans (Starter = KES 350, Business = KES 1000)
export const PLAN_PRICES_BASE_KES = {
    starter: 350,
    business: 1000,
    enterprise: null, // Custom
};

// Dashboard preview sample figures (base KES equivalents)
export const PREVIEW_BASE_KES = {
    pppoeToday: 12400,
    hotspotToday: 6850,
    totalRevenue: 398835,
    pendingDue: 34568,
    toastAmount: 50,
};

/**
 * Format a base KES amount into the visitor's selected currency.
 */
export function formatPrice(amountInKes, currencyCode = DEFAULT_CURRENCY) {
    const cfg = CURRENCIES[currencyCode] || CURRENCIES[DEFAULT_CURRENCY];
    const raw = amountInKes * cfg.rateFromKes;
    const rounded = raw >= 100 ? Math.round(raw) : Math.max(1, Math.round(raw));
    return `${cfg.symbol}${rounded.toLocaleString('en-US')}`;
}

