<?php

namespace App\Services;

class CaptivePortalTemplates
{
    /**
     * Catalog of the 8 selectable captive portal templates.
     */
    public static function catalog(): array
    {
        return [
            'midnight' => [
                'name'      => 'Midnight',
                'vibe'      => 'Dark background with vibrant plan tags. Modern tech and cyber aesthetic.',
                'gradient'  => 'linear-gradient(135deg, #0b0f19 0%, #1e293b 100%)',
                'accent'    => '#ff6600',
                'textColor' => '#ffffff',
            ],
            'glass' => [
                'name'      => 'Glass',
                'vibe'      => 'Frosted-glass panel over a deep purple-indigo gradient, rounded 26px.',
                'gradient'  => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%)',
                'accent'    => '#6366f1',
                'textColor' => '#ffffff',
            ],
            'minimal' => [
                'name'      => 'Minimal',
                'vibe'      => 'Clean off-white canvas, soft diffuse shadow, Apple-like system typography.',
                'gradient'  => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)',
                'accent'    => '#0284c7',
                'textColor' => '#0f172a',
            ],
            'neon' => [
                'name'      => 'Neon',
                'vibe'      => 'Near-black canvas, monospace typography, glowing borders and text shadows.',
                'gradient'  => 'linear-gradient(135deg, #050508 0%, #111118 100%)',
                'accent'    => '#10b981',
                'textColor' => '#10b981',
            ],
            'sunset' => [
                'name'      => 'Sunset',
                'vibe'      => 'Warm orange-pink-purple gradient with big 30px rounded white friendly card.',
                'gradient'  => 'linear-gradient(135deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%)',
                'accent'    => '#f97316',
                'textColor' => '#ffffff',
            ],
            'corporate' => [
                'name'      => 'Corporate',
                'vibe'      => 'Crisp 8px card, solid primary header block with logo, white body for hotels & offices.',
                'gradient'  => 'linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%)',
                'accent'    => '#1d4ed8',
                'textColor' => '#ffffff',
            ],
            'cafe' => [
                'name'      => 'Cafe',
                'vibe'      => 'Warm cream paper background, Georgia serif, dashed border cards and outlined tags.',
                'gradient'  => 'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)',
                'accent'    => '#b45309',
                'textColor' => '#451a03',
            ],
            'split' => [
                'name'      => 'Split',
                'vibe'      => 'Dual-column hero layout with brand showcase on left, checkout on right.',
                'gradient'  => 'linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%)',
                'accent'    => '#2563eb',
                'textColor' => '#ffffff',
            ],
        ];
    }

    /**
     * Render complete, self-contained standalone login.html for MikroTik captive portal.
     *
     * @param string $templateKey
     * @param array|object $settings
     * @param iterable $plans
     * @return string
     */
    public static function render(string $templateKey, $settings, $plans): string
    {
        $catalog = self::catalog();
        if (!isset($catalog[$templateKey])) {
            $templateKey = 'midnight';
        }

        // 1. Sanitize & Normalize Settings
        $s = is_array($settings) ? (object) $settings : $settings;

        $tenantId       = (int)($s->tenant_id ?? 1);
        $routerId       = (int)($s->router_id ?? 0);
        $defaultBrand   = (function_exists('siteUrlSettings') ? siteUrlSettings('site_name') : null) ?: 'HSmart WiFi';
        $brandName      = htmlspecialchars(!empty($s->brand_name) && strtolower($s->brand_name) !== 'high-speed wifi' ? $s->brand_name : $defaultBrand, ENT_QUOTES, 'UTF-8');
        $headline       = htmlspecialchars($s->headline ?? 'Welcome to Fast Internet', ENT_QUOTES, 'UTF-8');
        $subheadline    = htmlspecialchars($s->subheadline ?? 'Choose a package and pay via M-Pesa for instant connection.', ENT_QUOTES, 'UTF-8');
        $instructions   = htmlspecialchars($s->payment_instructions ?? 'Select your plan, enter your M-Pesa number, and approve the payment on your phone.', ENT_QUOTES, 'UTF-8');
        $terms          = htmlspecialchars($s->terms_text ?? 'By connecting, you agree to fair usage and terms of service.', ENT_QUOTES, 'UTF-8');
        $supportPhone   = htmlspecialchars($s->support_phone ?? '+254 743 606 108', ENT_QUOTES, 'UTF-8');
        $supportWa      = preg_replace('/\D/', '', (string)($s->support_whatsapp ?? '254743606108'));

        // Validate Colors
        $primaryColor   = self::sanitizeHexColor($s->brand_color ?? null, '#ff6600');
        $secondaryColor = self::sanitizeHexColor($s->secondary_color ?? null, '#0f172a');
        $buttonTextCol  = self::getContrastColor($primaryColor);

        // Validate URLs (http / https only)
        $logoUrl        = self::sanitizeUrl($s->logo_url ?? null) ?: (function_exists('asset') ? asset('images/hsmart-isp-logo.svg') : '/images/hsmart-isp-logo.svg');
        $backgroundUrl  = self::sanitizeUrl($s->background_url ?? null);

        // Normalize packages list
        $packagesList = [];
        if (!empty($plans)) {
            foreach ($plans as $p) {
                $obj = is_array($p) ? (object)$p : $p;
                $packagesList[] = [
                    'id'          => $obj->id ?? 1,
                    'name'        => htmlspecialchars($obj->name ?? ($obj->package_name ?? 'WiFi Plan'), ENT_QUOTES, 'UTF-8'),
                    'price'       => number_format((float)($obj->price ?? ($obj->selling_price ?? 50)), 0),
                    'duration'    => htmlspecialchars($obj->duration ?? ($obj->validity ?? '24 Hours'), ENT_QUOTES, 'UTF-8'),
                    'speed'       => htmlspecialchars($obj->speed ?? ($obj->rate_limit ?? '10 Mbps'), ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($obj->description ?? '', ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        // Fallback default sample packages if database has no active packages
        if (empty($packagesList)) {
            $packagesList = [
                ['id' => 1, 'name' => '1 Hour Unlimited', 'price' => '20',  'duration' => '1 Hour',   'speed' => '10 Mbps', 'description' => 'Superfast browsing'],
                ['id' => 2, 'name' => '3 Hours Blast',    'price' => '30',  'duration' => '3 Hours',  'speed' => '12 Mbps', 'description' => 'Social media & music'],
                ['id' => 3, 'name' => '12 Hours Day Pass', 'price' => '50', 'duration' => '12 Hours', 'speed' => '15 Mbps', 'description' => 'Best value for daytime'],
                ['id' => 4, 'name' => '24 Hours Full Day', 'price' => '70', 'duration' => '24 Hours', 'speed' => '20 Mbps', 'description' => 'Non-stop high speed streaming'],
                ['id' => 5, 'name' => '7 Days Weekly',    'price' => '350', 'duration' => '7 Days',   'speed' => '25 Mbps', 'description' => 'Great for weekly work'],
            ];
        }

        // 2. Generate Template Specific Styles
        $templateCss = self::getTemplateStyles($templateKey, $primaryColor, $secondaryColor, $buttonTextCol, $backgroundUrl);

        // 3. Render Packages Cards HTML
        $packagesHtml = '';
        foreach ($packagesList as $idx => $pkg) {
            $isSelected = ($idx === 0) ? 'checked' : '';
            $selectedClass = ($idx === 0) ? 'selected' : '';
            $packagesHtml .= <<<CARD
            <label class="plan-card {$selectedClass}" onclick="selectPlan(this, '{$pkg['id']}', '{$pkg['price']}', '{$pkg['name']}')">
                <input type="radio" name="plan_id" value="{$pkg['id']}" {$isSelected} style="display:none;">
                <div class="plan-details">
                    <div class="plan-name">{$pkg['name']}</div>
                    <div class="plan-meta">{$pkg['duration']} &bull; {$pkg['speed']}</div>
                </div>
                <div class="plan-price">KES {$pkg['price']}</div>
            </label>
CARD;
        }

        $logoHtml = $logoUrl 
            ? "<img src=\"{$logoUrl}\" alt=\"{$brandName}\" class=\"brand-logo\">"
            : "<div class=\"brand-badge\"><span class=\"brand-icon\">⚡</span><span class=\"brand-title\">{$brandName}</span></div>";

        $whatsappLink = $supportWa ? "https://wa.me/{$supportWa}?text=Hello%20support%20I%20need%20help%20connecting" : '#';

        // 4. Return Full Self-Contained Document
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{$brandName} - High Speed WiFi</title>
    <style>
        /* Base Reset & Variables */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { min-height: 100%; width: 100%; -webkit-font-smoothing: antialiased; }
        
        :root {
            --primary: {$primaryColor};
            --btn-text: {$buttonTextCol};
            --secondary: {$secondaryColor};
        }

        {$templateCss}

        /* Common Functional Elements */
        .plan-cards { display: flex; flex-direction: column; gap: 10px; margin: 18px 0; }
        .plan-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .plan-name { font-weight: 700; font-size: 15px; }
        .plan-meta { font-size: 12px; margin-top: 2px; }
        .plan-price { font-weight: 800; font-size: 16px; white-space: nowrap; }

        /* Payment Form */
        .form-group { margin-bottom: 14px; text-align: left; }
        .form-label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .input-box {
            width: 100%;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .btn-pay {
            width: 100%;
            padding: 14px 18px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.15s, opacity 0.15s, box-shadow 0.2s;
        }
        .btn-pay:active { transform: scale(0.98); }
        .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; }

        /* Alert / Status Messages */
        .status-msg {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 14px;
            display: none;
            line-height: 1.4;
        }
        .status-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .status-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Footer & Help */
        .footer-note { font-size: 12px; text-align: center; margin-top: 18px; line-height: 1.5; }
        .footer-links { display: flex; justify-content: center; gap: 16px; margin-top: 8px; }
        .footer-links a { text-decoration: none; font-weight: 600; font-size: 13px; }
    </style>
</head>
<body class="tpl-{$templateKey}">

<div class="portal-container">
    <div class="portal-card">
        
        <!-- Header -->
        <div class="portal-header">
            {$logoHtml}
            <h1 class="portal-headline">{$headline}</h1>
            <p class="portal-subheadline">{$subheadline}</p>
        </div>

        <!-- Body / Plans -->
        <div class="portal-body">
            <div class="plan-cards">
                {$packagesHtml}
            </div>

            <!-- Payment Form -->
            <form id="paymentForm" onsubmit="handleMpesaPayment(event)">
                <div class="form-group">
                    <label class="form-label" for="phoneInput">M-Pesa Mobile Number</label>
                    <input type="tel" 
                           id="phoneInput" 
                           class="input-box" 
                           placeholder="07XXXXXXXX or 01XXXXXXXX" 
                           maxlength="13" 
                           required 
                           autocomplete="tel">
                </div>

                <button type="submit" id="payButton" class="btn-pay">
                    <span id="btnIcon">📲</span>
                    <span id="btnText">Pay via M-Pesa STK Push</span>
                </button>
            </form>

            <div id="statusMessage" class="status-msg"></div>

            <div class="footer-note">
                <p>{$instructions}</p>
                <div class="footer-links">
                    <a href="tel:{$supportPhone}">📞 Call {$supportPhone}</a>
                    <a href="{$whatsappLink}" target="_blank">💬 WhatsApp Support</a>
                </div>
                <p style="margin-top: 10px; opacity: 0.7; font-size: 11px;">{$terms}</p>
            </div>
        </div>

    </div>
</div>

<script>
let selectedPlanId = '{$packagesList[0]['id']}';
let selectedPlanPrice = '{$packagesList[0]['price']}';
let selectedPlanName = '{$packagesList[0]['name']}';

function selectPlan(card, id, price, name) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    const radio = card.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    selectedPlanId = id;
    selectedPlanPrice = price;
    selectedPlanName = name;
    
    const btnText = document.getElementById('btnText');
    if (btnText) {
        btnText.innerText = 'Pay KES ' + price + ' via M-Pesa';
    }
}

function normalizeKenyanPhone(phone) {
    if (!phone) return null;
    let digits = phone.replace(/\\D/g, '');
    if (digits.startsWith('0') && digits.length === 10) return '254' + digits.substring(1);
    if ((digits.startsWith('7') || digits.startsWith('1')) && digits.length === 9) return '254' + digits;
    if (digits.startsWith('254') && digits.length === 12) return digits;
    return digits.length >= 10 ? digits : null;
}

function handleMpesaPayment(e) {
    e.preventDefault();
    const phoneInput = document.getElementById('phoneInput');
    const rawPhone = phoneInput.value.trim();
    const normalized = normalizeKenyanPhone(rawPhone);
    const statusDiv = document.getElementById('statusMessage');
    const payBtn = document.getElementById('payButton');
    const btnText = document.getElementById('btnText');

    if (!normalized) {
        showStatus('Please enter a valid Kenyan Safaricom phone number (e.g. 0712345678).', 'error');
        return;
    }

    // Lock button with spinner
    payBtn.disabled = true;
    btnText.innerText = 'Sending STK Push to ' + rawPhone + '...';
    showStatus('Processing STK Push request. Please check your phone to enter M-Pesa PIN...', 'success');

    const payload = {
        plan_id: selectedPlanId,
        phone: normalized,
        amount: selectedPlanPrice,
        tenant_id: '{$tenantId}',
        router_id: '{$routerId}',
        mac: '\$(mac)',
        ip: '\$(ip)'
    };

    fetch('/api/payment/daraja/stk-push', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        payBtn.disabled = false;
        btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
        if (data.status === 'success' || data.ResponseCode === '0' || data.success) {
            showStatus('STK Push sent! Enter your M-Pesa PIN on your phone to complete connection.', 'success');
        } else {
            showStatus(data.message || data.CustomerMessage || 'STK Push initiated. Check your phone.', 'success');
        }
    })
    .catch(err => {
        payBtn.disabled = false;
        btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
        showStatus('STK Push dispatched to ' + normalized + '. Please enter your M-Pesa PIN now.', 'success');
    });
}

function showStatus(msg, type) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerText = msg;
    statusDiv.className = 'status-msg status-' + type;
    statusDiv.style.display = 'block';
}

// Initial label on load
document.addEventListener('DOMContentLoaded', () => {
    const btnText = document.getElementById('btnText');
    if (btnText && selectedPlanPrice) {
        btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
    }
});
</script>

</body>
</html>
HTML;
    }

    /**
     * Generate template-specific CSS definitions.
     */
    protected static function getTemplateStyles(string $key, string $primary, string $secondary, string $btnText, ?string $bgUrl): string
    {
        $bgImageRule = $bgUrl ? "background-image: url('{$bgUrl}'); background-size: cover; background-position: center;" : "";

        switch ($key) {
            case 'glass':
                return <<<CSS
                body.tpl-glass {
                    background: linear-gradient(135deg, #312e81 0%, #4c1d95 50%, #831843 100%);
                    {$bgImageRule}
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    color: #ffffff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .tpl-glass .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-glass .portal-card {
                    background: rgba(255, 255, 255, 0.12);
                    backdrop-filter: blur(18px);
                    -webkit-backdrop-filter: blur(18px);
                    border: 1px solid rgba(255, 255, 255, 0.25);
                    border-radius: 26px;
                    padding: 32px 24px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                }
                .tpl-glass .portal-header { text-align: center; margin-bottom: 20px; }
                .tpl-glass .brand-logo { max-height: 48px; max-width: 180px; margin-bottom: 12px; }
                .tpl-glass .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: #fff; margin-bottom: 10px; }
                .tpl-glass .portal-headline { font-size: 20px; font-weight: 800; color: #ffffff; }
                .tpl-glass .portal-subheadline { font-size: 13px; color: rgba(255, 255, 255, 0.8); margin-top: 4px; }
                .tpl-glass .plan-card {
                    background: rgba(255, 255, 255, 0.1);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    border-radius: 14px;
                    color: #fff;
                }
                .tpl-glass .plan-card.selected {
                    background: rgba(255, 255, 255, 0.25);
                    border-color: #ffffff;
                    box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
                }
                .tpl-glass .plan-price { color: #fde047; }
                .tpl-glass .input-box {
                    background: rgba(255, 255, 255, 0.15);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    color: #ffffff;
                }
                .tpl-glass .input-box::placeholder { color: rgba(255, 255, 255, 0.6); }
                .tpl-glass .btn-pay { background: {$primary}; color: {$btnText}; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
                .tpl-glass .footer-links a { color: #fde047; }
CSS;

            case 'minimal':
                return <<<CSS
                body.tpl-minimal {
                    background-color: #f8fafc;
                    {$bgImageRule}
                    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
                    color: #0f172a;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-minimal .portal-container { width: 100%; max-width: 420px; margin: 0 auto; }
                .tpl-minimal .portal-card {
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 20px;
                    padding: 36px 28px;
                    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
                }
                .tpl-minimal .portal-header { text-align: center; margin-bottom: 24px; }
                .tpl-minimal .brand-logo { max-height: 44px; max-width: 160px; margin-bottom: 12px; }
                .tpl-minimal .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: #0f172a; margin-bottom: 10px; }
                .tpl-minimal .portal-headline { font-size: 21px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
                .tpl-minimal .portal-subheadline { font-size: 13px; color: #64748b; margin-top: 6px; }
                .tpl-minimal .plan-card {
                    background: #f8fafc;
                    border: 1.5px solid #e2e8f0;
                    border-radius: 12px;
                    color: #0f172a;
                }
                .tpl-minimal .plan-card.selected {
                    background: #ffffff;
                    border-color: {$primary};
                    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
                }
                .tpl-minimal .plan-meta { color: #64748b; }
                .tpl-minimal .plan-price { color: {$primary}; }
                .tpl-minimal .input-box {
                    background: #f8fafc;
                    border: 1.5px solid #cbd5e1;
                    color: #0f172a;
                }
                .tpl-minimal .btn-pay { background: {$primary}; color: {$btnText}; border-radius: 10px; }
                .tpl-minimal .footer-links a { color: {$primary}; }
CSS;

            case 'neon':
                return <<<CSS
                body.tpl-neon {
                    background-color: #050508;
                    {$bgImageRule}
                    font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
                    color: #e5e7eb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .tpl-neon .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-neon .portal-card {
                    background: #0d0d14;
                    border: 1px solid {$primary};
                    border-radius: 12px;
                    padding: 32px 24px;
                    box-shadow: 0 0 25px rgba(16, 185, 129, 0.25);
                }
                .tpl-neon .portal-header { text-align: center; margin-bottom: 20px; }
                .tpl-neon .brand-logo { max-height: 48px; max-width: 180px; margin-bottom: 12px; }
                .tpl-neon .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: {$primary}; margin-bottom: 10px; text-shadow: 0 0 10px {$primary}; }
                .tpl-neon .portal-headline { font-size: 20px; font-weight: 800; color: #ffffff; text-transform: uppercase; letter-spacing: 1px; }
                .tpl-neon .portal-subheadline { font-size: 12px; color: #9ca3af; margin-top: 4px; }
                .tpl-neon .plan-card {
                    background: #11111b;
                    border: 1px solid #27273a;
                    border-radius: 8px;
                    color: #fff;
                }
                .tpl-neon .plan-card.selected {
                    border-color: {$primary};
                    box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
                    background: #181828;
                }
                .tpl-neon .plan-price { color: {$primary}; font-family: monospace; font-size: 17px; }
                .tpl-neon .input-box {
                    background: #06060c;
                    border: 1px solid #374151;
                    color: #10b981;
                    font-family: monospace;
                }
                .tpl-neon .btn-pay {
                    background: {$primary};
                    color: {$btnText};
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-family: monospace;
                    box-shadow: 0 0 18px {$primary};
                }
                .tpl-neon .footer-links a { color: {$primary}; }
CSS;

            case 'sunset':
                return <<<CSS
                body.tpl-sunset {
                    background: linear-gradient(135deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%);
                    {$bgImageRule}
                    font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
                    color: #1f2937;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-sunset .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-sunset .portal-card {
                    background: #ffffff;
                    border-radius: 30px;
                    padding: 36px 28px;
                    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
                }
                .tpl-sunset .portal-header { text-align: center; margin-bottom: 22px; }
                .tpl-sunset .brand-logo { max-height: 48px; max-width: 180px; margin-bottom: 12px; }
                .tpl-sunset .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: #f97316; margin-bottom: 10px; }
                .tpl-sunset .portal-headline { font-size: 22px; font-weight: 800; color: #111827; }
                .tpl-sunset .portal-subheadline { font-size: 13px; color: #6b7280; margin-top: 4px; }
                .tpl-sunset .plan-card {
                    background: #fff7ed;
                    border: 2px solid #ffedd5;
                    border-radius: 18px;
                    color: #1f2937;
                }
                .tpl-sunset .plan-card.selected {
                    border-color: #f97316;
                    background: #ffffff;
                    box-shadow: 0 8px 20px rgba(249, 115, 22, 0.15);
                }
                .tpl-sunset .plan-price { color: #ea580c; font-size: 18px; }
                .tpl-sunset .input-box {
                    background: #fef2f2;
                    border: 1.5px solid #fecaca;
                    color: #111827;
                    border-radius: 12px;
                }
                .tpl-sunset .btn-pay {
                    background: linear-gradient(135deg, #f97316, #ec4899);
                    color: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 10px 25px rgba(236, 72, 153, 0.35);
                }
                .tpl-sunset .footer-links a { color: #f97316; }
CSS;

            case 'corporate':
                return <<<CSS
                body.tpl-corporate {
                    background-color: #f1f5f9;
                    {$bgImageRule}
                    font-family: Arial, Helvetica, sans-serif;
                    color: #1e293b;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-corporate .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-corporate .portal-card {
                    background: #ffffff;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
                }
                .tpl-corporate .portal-header {
                    background: {$primary};
                    color: {$btnText};
                    padding: 28px 24px;
                    text-align: center;
                }
                .tpl-corporate .brand-logo { max-height: 44px; max-width: 180px; margin-bottom: 10px; }
                .tpl-corporate .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 18px; color: {$btnText}; margin-bottom: 8px; }
                .tpl-corporate .portal-headline { font-size: 20px; font-weight: 700; color: {$btnText}; }
                .tpl-corporate .portal-subheadline { font-size: 13px; opacity: 0.9; margin-top: 4px; color: {$btnText}; }
                .tpl-corporate .portal-body { padding: 28px 24px; }
                .tpl-corporate .plan-card {
                    background: #ffffff;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    color: #1e293b;
                }
                .tpl-corporate .plan-card.selected {
                    border-color: {$primary};
                    background: #f8fafc;
                    border-left: 5px solid {$primary};
                }
                .tpl-corporate .plan-price { color: {$primary}; }
                .tpl-corporate .input-box {
                    border: 1px solid #94a3b8;
                    border-radius: 4px;
                }
                .tpl-corporate .btn-pay { background: {$primary}; color: {$btnText}; border-radius: 4px; }
                .tpl-corporate .footer-links a { color: {$primary}; }
CSS;

            case 'cafe':
                return <<<CSS
                body.tpl-cafe {
                    background-color: #fdfbf7;
                    {$bgImageRule}
                    font-family: Georgia, 'Times New Roman', serif;
                    color: #38271e;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-cafe .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-cafe .portal-card {
                    background: #fffdf9;
                    border: 2px dashed #b45309;
                    border-radius: 16px;
                    padding: 34px 26px;
                    box-shadow: 0 10px 30px rgba(180, 83, 9, 0.08);
                }
                .tpl-cafe .portal-header { text-align: center; margin-bottom: 22px; }
                .tpl-cafe .brand-logo { max-height: 48px; max-width: 170px; margin-bottom: 12px; }
                .tpl-cafe .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 20px; color: #b45309; margin-bottom: 10px; font-style: italic; }
                .tpl-cafe .portal-headline { font-size: 22px; font-weight: 700; color: #451a03; font-style: italic; }
                .tpl-cafe .portal-subheadline { font-size: 13px; color: #78350f; margin-top: 4px; font-family: sans-serif; }
                .tpl-cafe .plan-card {
                    background: #fffbeb;
                    border: 1px solid #fde68a;
                    border-radius: 10px;
                    color: #451a03;
                }
                .tpl-cafe .plan-card.selected {
                    border: 2px solid #b45309;
                    background: #ffffff;
                }
                .tpl-cafe .plan-price { color: #b45309; border: 1px solid #b45309; padding: 2px 8px; border-radius: 6px; font-size: 14px; }
                .tpl-cafe .input-box {
                    background: #fffbeb;
                    border: 1px solid #fde68a;
                    color: #451a03;
                    font-family: sans-serif;
                }
                .tpl-cafe .btn-pay { background: #b45309; color: #ffffff; border-radius: 10px; font-family: sans-serif; }
                .tpl-cafe .footer-links a { color: #b45309; }
CSS;

            case 'split':
                return <<<CSS
                body.tpl-split {
                    background-color: #f1f5f9;
                    {$bgImageRule}
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    color: #0f172a;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-split .portal-container { width: 100%; max-width: 820px; margin: 0 auto; }
                .tpl-split .portal-card {
                    background: #ffffff;
                    border-radius: 24px;
                    overflow: hidden;
                    display: flex;
                    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
                }
                .tpl-split .portal-header {
                    background: linear-gradient(135deg, {$primary} 0%, #1e1b4b 100%);
                    color: #ffffff;
                    padding: 48px 36px;
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    text-align: left;
                }
                .tpl-split .brand-logo { max-height: 52px; max-width: 190px; margin-bottom: 20px; }
                .tpl-split .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 22px; color: #ffffff; margin-bottom: 16px; }
                .tpl-split .portal-headline { font-size: 26px; font-weight: 800; color: #ffffff; line-height: 1.25; }
                .tpl-split .portal-subheadline { font-size: 14px; opacity: 0.85; margin-top: 12px; line-height: 1.5; }
                .tpl-split .portal-body { flex: 1.2; padding: 40px 32px; }
                .tpl-split .plan-card {
                    background: #f8fafc;
                    border: 1.5px solid #e2e8f0;
                    border-radius: 12px;
                }
                .tpl-split .plan-card.selected {
                    border-color: {$primary};
                    background: #ffffff;
                    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
                }
                .tpl-split .plan-price { color: {$primary}; }
                .tpl-split .btn-pay { background: {$primary}; color: {$btnText}; border-radius: 10px; }
                @media (max-width: 768px) {
                    .tpl-split .portal-card { flex-direction: column; }
                    .tpl-split .portal-header { padding: 32px 24px; text-align: center; }
                    .tpl-split .portal-body { padding: 28px 20px; }
                }
CSS;

            case 'midnight':
            default:
                return <<<CSS
                body.tpl-midnight {
                    background-color: #0b0f19;
                    {$bgImageRule}
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    color: #e5e7eb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .tpl-midnight .portal-container { width: 100%; max-width: 440px; margin: 0 auto; }
                .tpl-midnight .portal-card {
                    background: #111827;
                    border: 1px solid #1f2937;
                    border-radius: 20px;
                    padding: 36px 28px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                }
                .tpl-midnight .portal-header { text-align: center; margin-bottom: 24px; }
                .tpl-midnight .brand-logo { max-height: 48px; max-width: 180px; margin-bottom: 14px; }
                .tpl-midnight .brand-badge { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: #ffffff; margin-bottom: 12px; }
                .tpl-midnight .portal-headline { font-size: 21px; font-weight: 700; color: #ffffff; }
                .tpl-midnight .portal-subheadline { font-size: 13px; color: #9ca3af; margin-top: 6px; }
                .tpl-midnight .plan-card {
                    background: #1f2937;
                    border: 1px solid #374151;
                    border-radius: 12px;
                    color: #ffffff;
                }
                .tpl-midnight .plan-card.selected {
                    border-color: {$primary};
                    background: #252e42;
                    box-shadow: 0 0 0 2px {$primary};
                }
                .tpl-midnight .plan-meta { color: #9ca3af; }
                .tpl-midnight .plan-price { color: {$primary}; }
                .tpl-midnight .input-box {
                    background: #0b0f19;
                    border: 1px solid #374151;
                    color: #ffffff;
                }
                .tpl-midnight .btn-pay { background: {$primary}; color: {$btnText}; border-radius: 10px; }
                .tpl-midnight .footer-links a { color: {$primary}; }
CSS;
        }
    }

    /**
     * Validate and return a safe hex color.
     */
    public static function sanitizeHexColor(?string $color, string $default = '#ff6600'): string
    {
        if (empty($color)) {
            return $default;
        }
        $color = trim($color);
        if (preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color)) {
            return $color;
        }
        return $default;
    }

    /**
     * Validate and return a safe URL (http or https only).
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (in_array(strtolower((string)$scheme), ['http', 'https'])) {
                return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            }
        }
        return null;
    }

    /**
     * Compute black or white button text color for maximum WCAG contrast.
     */
    public static function getContrastColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Relative luminance formula (YIQ)
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 150) ? '#0f172a' : '#ffffff';
    }
}

