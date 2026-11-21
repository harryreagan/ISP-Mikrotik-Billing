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
                $rawName = $obj->package ?? ($obj->name ?? ($obj->package_name ?? 'WiFi Plan'));
                $rawDuration = $obj->duration ?? ($obj->validity ?? null);
                if (!$rawDuration) {
                    $vVal = $obj->validity_value ?? ($obj->duration_value ?? null);
                    $vUnit = $obj->validity_unit ?? ($obj->duration_unit ?? null);
                    if ($vVal && $vUnit) {
                        $unitLabel = ucfirst(strtolower((string)$vUnit));
                        if ((int)$vVal === 1 && str_ends_with($unitLabel, 's')) {
                            $unitLabel = substr($unitLabel, 0, -1);
                        }
                        $rawDuration = "{$vVal} {$unitLabel}";
                    } else {
                        $rawDuration = '24 Hours';
                    }
                }
                $rawSpeed = $obj->speed ?? null;
                if (!$rawSpeed && !empty($obj->speed_download)) {
                    $rawSpeed = $obj->speed_download . ' ' . ($obj->speed_download_unit ?? 'Mbps');
                }
                $rawSpeed = $rawSpeed ?: ($obj->rate_limit ?? '10 Mbps');

                $includesTv = !empty($obj->includes_tv) || ($obj->type ?? '') === 'tv';
                $tvChannels = $includesTv ? htmlspecialchars((string)($obj->tv_channels ?: 'Smart TV & Live Channels'), ENT_QUOTES, 'UTF-8') : '';

                $packagesList[] = [
                    'id'          => $obj->id ?? 1,
                    'name'        => htmlspecialchars((string)$rawName, ENT_QUOTES, 'UTF-8'),
                    'price'       => number_format((float)($obj->price ?? ($obj->selling_price ?? 50)), 0),
                    'duration'    => htmlspecialchars((string)$rawDuration, ENT_QUOTES, 'UTF-8'),
                    'speed'       => htmlspecialchars((string)$rawSpeed, ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars((string)($obj->description ?? ''), ENT_QUOTES, 'UTF-8'),
                    'includes_tv' => $includesTv,
                    'tv_channels' => $tvChannels,
                ];
            }
        }

        // Fallback default sample packages if database has no active packages
        if (empty($packagesList)) {
            $packagesList = [
                ['id' => 1, 'name' => '1 Hour Unlimited', 'price' => '20',  'duration' => '1 Hour',   'speed' => '10 Mbps', 'description' => 'Superfast browsing', 'includes_tv' => false, 'tv_channels' => ''],
                ['id' => 2, 'name' => '3 Hours Blast',    'price' => '30',  'duration' => '3 Hours',  'speed' => '12 Mbps', 'description' => 'Social media & music', 'includes_tv' => false, 'tv_channels' => ''],
                ['id' => 3, 'name' => '12 Hours Day Pass', 'price' => '50', 'duration' => '12 Hours', 'speed' => '15 Mbps', 'description' => 'Best value for daytime', 'includes_tv' => false, 'tv_channels' => ''],
                ['id' => 4, 'name' => '24 Hours Full Day', 'price' => '70', 'duration' => '24 Hours', 'speed' => '20 Mbps', 'description' => 'Non-stop high speed streaming', 'includes_tv' => false, 'tv_channels' => ''],
                ['id' => 5, 'name' => '24h WiFi + Smart TV', 'price' => '100', 'duration' => '24 Hours', 'speed' => '25 Mbps', 'description' => 'Live sports, entertainment & movies', 'includes_tv' => true, 'tv_channels' => 'Smart TV & Live Channels'],
                ['id' => 6, 'name' => '7 Days Weekly',    'price' => '350', 'duration' => '7 Days',   'speed' => '25 Mbps', 'description' => 'Great for weekly work', 'includes_tv' => false, 'tv_channels' => ''],
            ];
        }

        // 2. Generate Template Specific Styles
        $templateCss = self::getTemplateStyles($templateKey, $primaryColor, $secondaryColor, $buttonTextCol, $backgroundUrl);

        // 3. Render Packages Cards HTML
        $packagesHtml = '';
        foreach ($packagesList as $idx => $pkg) {
            $selectedClass = ($idx === 0) ? 'selected' : '';
            $rawJsName = addslashes(htmlspecialchars_decode((string) $pkg['name'], ENT_QUOTES));
            $tvBadgeHtml = !empty($pkg['includes_tv'])
                ? '<div style="margin-top:3px;"><span style="display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; background:#4f46e5; color:#ffffff; padding:2px 7px; border-radius:999px;">📺 ' . $pkg['tv_channels'] . '</span></div>'
                : '';
            $packagesHtml .= <<<CARD
            <div class="plan-card {$selectedClass}" data-id="{$pkg['id']}" data-price="{$pkg['price']}" data-name="{$pkg['name']}" onclick="selectPlan(this, '{$pkg['id']}', '{$pkg['price']}', '{$rawJsName}')" style="cursor: pointer; -webkit-tap-highlight-color: transparent;">
                <div class="plan-details">
                    <div class="plan-name">{$pkg['name']}</div>
                    <div class="plan-meta">{$pkg['duration']} &bull; {$pkg['speed']}</div>
                    {$tvBadgeHtml}
                </div>
                <div class="plan-price">KES {$pkg['price']}</div>
            </div>
CARD;
        }

        $tvPackagesOptionsHtml = '';
        foreach ($packagesList as $pkg) {
            $tvPackagesOptionsHtml .= "<option value=\"{$pkg['id']}\" data-price=\"{$pkg['price']}\" data-name=\"{$pkg['name']}\">{$pkg['name']} - KES {$pkg['price']}</option>\n";
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
            <form id="paymentForm" onsubmit="event.preventDefault(); handleMpesaPayment(); return false;">
                <div class="form-group">
                    <label class="form-label" for="phoneInput">M-Pesa Mobile Number</label>
                    <input type="tel" 
                           id="phoneInput" 
                           class="input-box" 
                           placeholder="07XXXXXXXX or 01XXXXXXXX" 
                           maxlength="13" 
                           autocomplete="tel">
                </div>

                <button type="button" id="payButton" class="btn-pay" onclick="handleMpesaPayment()">
                    <span id="btnIcon">📲</span>
                    <span id="btnText">Pay via M-Pesa STK Push</span>
                </button>
            </form>

            <div id="statusMessage" class="status-msg"></div>

            <!-- Connect TV / Device Button (Smart TV, Console, Apple TV bypass) -->
            <div style="margin-top: 14px;">
                <button type="button" onclick="openTvModal()" style="width: 100%; padding: 13px 18px; background: #eab308; color: #0f172a; border: none; border-radius: 10px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); transition: transform 0.15s, background 0.15s; -webkit-tap-highlight-color: transparent;">
                    <span style="font-size: 18px;">📺</span>
                    <span>Connect TV Here</span>
                </button>
            </div>

            <!-- Voucher / Access Code Login Form (Works on MikroTik Hotspot & Cloud Portal) -->
            <div style="margin-top: 18px; padding-top: 16px; border-top: 1px dashed rgba(148, 163, 184, 0.35);">
                <form id="voucherLoginForm" name="login" action="$(link-login-only)" method="post" onsubmit="return handleVoucherSubmit(event)">
                    <input type="hidden" name="dst" value="$(link-orig)" />
                    <input type="hidden" name="popup" value="true" />
                    <label class="form-label" for="voucherCodeInput">Have a Voucher or M-Pesa Receipt Code?</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text"
                               id="voucherCodeInput"
                               name="username"
                               class="input-box"
                               placeholder="Enter Voucher / Receipt Code"
                               autocapitalize="characters"
                               oninput="this.value = this.value.toUpperCase().trim(); document.getElementById('voucherPassInput').value = this.value;"
                               style="flex: 1; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">
                        <input type="hidden" id="voucherPassInput" name="password" value="" />
                        <button type="submit" class="btn-pay" style="width: auto; padding: 0 18px; white-space: nowrap; font-size: 14px;">
                            Connect &rarr;
                        </button>
                    </div>
                </form>
            </div>

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

<!-- Modal: Connect TV / Device -->
<div id="tvModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:999999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); box-sizing:border-box;">
    <div style="background:#ffffff; color:#0f172a; width:100%; max-width:440px; border-radius:16px; padding:22px 20px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3); box-sizing:border-box; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
            <h2 style="font-size:20px; font-weight:800; color:#0f172a; margin:0; line-height:1.2;">Connect Your TV / Device</h2>
            <button type="button" onclick="closeTvModal()" style="background:transparent; border:none; font-size:24px; font-weight:700; color:#64748b; cursor:pointer; line-height:1; padding:0 4px;">&times;</button>
        </div>

        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 12px; margin-bottom:12px; font-size:12px; line-height:1.45; color:#1e40af;">
            <strong>Tip:</strong> If your TV can open this page, you don't need to enter a MAC address. Just click on a package above and buy normally. Only use this form if your TV cannot show the sign-in page.
        </div>

        <p style="font-size:12.5px; color:#64748b; text-align:center; margin-bottom:12px;">
            Enter the MAC address from your TV settings, choose a package, and pay.
        </p>

        <details style="margin-bottom:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:8px 10px; font-size:12px; color:#334155;">
            <summary style="font-weight:700; cursor:pointer; color:#0284c7; outline:none; user-select:none;">
                ▶ How do I find my TV MAC address?
            </summary>
            <div style="margin-top:8px; font-size:11.5px; line-height:1.5; color:#475569;">
                <ul style="padding-left:18px; margin:0;">
                    <li><strong>Samsung:</strong> Settings &gt; General &gt; Network &gt; Network Status</li>
                    <li><strong>LG webOS:</strong> Settings &gt; Network &gt; Wi-Fi Connection &gt; Advanced</li>
                    <li><strong>Android / Google TV:</strong> Settings &gt; Network &amp; Internet &gt; [Wi-Fi] &gt; MAC</li>
                    <li><strong>Fire TV / Roku:</strong> Settings &gt; System / Network &gt; About &gt; MAC</li>
                    <li>Or check the sticker on the back of your TV / media box.</li>
                </ul>
            </div>
        </details>

        <div class="form-group" style="margin-bottom:12px; text-align:left;">
            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Device MAC Address</label>
            <input type="text" id="tvMacInput" class="input-box" placeholder="e.g. AA:BB:CC:DD:EE:FF" maxlength="17" autocapitalize="characters" oninput="handleTvMacInput(this)" style="font-family:monospace; font-weight:700; letter-spacing:1px; text-transform:uppercase; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; width:100%; font-size:14px; color:#0f172a; background:#fff; box-sizing:border-box;">
        </div>

        <div style="margin-bottom:12px; text-align:left;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <label style="font-size:12px; font-weight:700; color:#334155;">Devices on this network</label>
                <button type="button" onclick="loadNetworkDevices(true)" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:2px 8px; font-size:11px; font-weight:600; color:#475569; cursor:pointer;">Refresh</button>
            </div>
            <div id="networkDevicesContainer" style="border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; padding:6px; max-height:130px; overflow-y:auto;">
                <div id="devicesListPlaceholder" style="font-size:12px; color:#94a3b8; padding:8px; text-align:center;">Looking for devices on this network...</div>
                <div id="devicesListItems" style="display:none; flex-direction:column; gap:4px;"></div>
            </div>
            <p style="font-size:11px; color:#94a3b8; margin-top:3px;">Start typing the MAC above to filter this list, then tap your device.</p>
        </div>

        <div class="form-group" style="margin-bottom:12px; text-align:left;">
            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Device Name <span style="font-weight:400; color:#94a3b8;">(optional)</span></label>
            <input type="text" id="tvNameInput" class="input-box" placeholder="e.g. Living Room TV" maxlength="40" style="border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; width:100%; font-size:13.5px; color:#0f172a; background:#fff; box-sizing:border-box;">
        </div>

        <div class="form-group" style="margin-bottom:12px; text-align:left;">
            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Select Package</label>
            <select id="tvPackageSelect" onchange="updateTvSelectedPackage()" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13.5px; color:#0f172a; background:#fff; font-weight:600; outline:none; box-sizing:border-box;">
                <option value="">-- Choose a package --</option>
                {$tvPackagesOptionsHtml}
            </select>
        </div>

        <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:8px 10px; margin-bottom:12px; font-size:11.5px; line-height:1.4; color:#9a3412;">
            <strong>One device per package.</strong> This package works on this one device only. Please do not buy one package for several devices.
        </div>

        <div class="form-group" style="margin-bottom:14px; text-align:left;">
            <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Phone Number (M-Pesa)</label>
            <input type="tel" id="tvPhoneInput" class="input-box" placeholder="e.g. 0712345678" maxlength="13" style="border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; width:100%; font-size:14px; color:#0f172a; background:#fff; box-sizing:border-box;">
        </div>

        <div id="tvStatusMsg" style="display:none; padding:10px 12px; border-radius:8px; font-size:12px; margin-bottom:12px; line-height:1.4;"></div>

        <div style="display:flex; gap:10px;">
            <button type="button" id="tvBindBtn" onclick="submitTvBindPayment()" style="flex:1; background:#10b981; color:#fff; border:none; border-radius:8px; padding:12px 14px; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;">
                <span id="tvBtnText">Bind &amp; Pay</span>
            </button>
            <button type="button" onclick="closeTvModal()" style="background:#64748b; color:#fff; border:none; border-radius:8px; padding:12px 18px; font-size:15px; font-weight:600; cursor:pointer;">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
let selectedPlanId = '{$packagesList[0]['id']}';
let selectedPlanPrice = '{$packagesList[0]['price']}';
let selectedPlanName = '{$packagesList[0]['name']}';
let stkPollTimer = null;
let tvSelectedPackageId = '';
let tvSelectedPackagePrice = '0';
let tvPollTimer = null;
let networkDevicesCache = [];

const apiBase = window.location.hostname.includes('hsmart.co.ke') ? '' : 'https://isp.hsmart.co.ke';
const urlParams = new URLSearchParams(window.location.search);

function getMikrotikVar(rawVal, paramName, fallback = '') {
    if (urlParams.get(paramName)) return urlParams.get(paramName);
    if (rawVal && !rawVal.startsWith('$(')) return rawVal;
    return fallback;
}

const clientMac = getMikrotikVar('\$(mac)', 'mac', '');
const clientIp = getMikrotikVar('\$(ip)', 'ip', '');
const linkLoginOnly = getMikrotikVar('\$(link-login-only)', 'link-login-only', urlParams.get('link_login') || 'http://10.5.50.1/login');
const linkOrig = getMikrotikVar('\$(link-orig)', 'link-orig', urlParams.get('link_orig') || 'https://www.google.com');
const mtError = getMikrotikVar('\$(error)', 'error', '');

let autoConnectTimer = null;

function cancelAllPendingTimers() {
    if (autoConnectTimer) {
        clearTimeout(autoConnectTimer);
        autoConnectTimer = null;
    }
    if (stkPollTimer) {
        clearInterval(stkPollTimer);
        stkPollTimer = null;
    }
}

function selectPlan(card, id, price, name) {
    cancelAllPendingTimers();
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    if (card) card.classList.add('selected');
    selectedPlanId = String(id);
    selectedPlanPrice = String(price);
    selectedPlanName = String(name);
    
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

function handleVoucherSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    cancelAllPendingTimers();
    const codeInput = document.getElementById('voucherCodeInput');
    const passInput = document.getElementById('voucherPassInput');
    const form = document.getElementById('voucherLoginForm');
    const submitBtn = document.getElementById('voucherSubmitBtn');

    if (!codeInput || !codeInput.value.trim()) {
        showStatus('Please enter your Mobile Number, Voucher, or M-Pesa Receipt Code.', 'error');
        return false;
    }
    const rawCode = codeInput.value.trim();
    if (submitBtn) submitBtn.disabled = true;
    showStatus('Verifying credentials...', 'success');

    const payload = {
        code: rawCode,
        mac: clientMac,
        ip: clientIp,
        router_id: '{$routerId}',
        tenant_id: '{$tenantId}'
    };

    fetch(apiBase + '/api/portal/returning-user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (submitBtn) submitBtn.disabled = false;
        if (res && res.status === 'active') {
            autoConnectHotspot(res.username || res.voucher_code, res.password || res.username || res.voucher_code);
        } else if (res && res.status === 'expired') {
            showStatus('⚠️ ' + (res.message || 'Package expired. Please select a plan to renew.'), 'error');
        } else {
            codeInput.value = rawCode.toUpperCase();
            if (passInput) passInput.value = rawCode.toUpperCase();
            const currentAction = form.getAttribute('action') || '';
            if (currentAction.startsWith('$(') || !currentAction) {
                form.setAttribute('action', linkLoginOnly);
            }
            form.submit();
        }
    })
    .catch(() => {
        if (submitBtn) submitBtn.disabled = false;
        codeInput.value = rawCode.toUpperCase();
        if (passInput) passInput.value = rawCode.toUpperCase();
        const currentAction = form.getAttribute('action') || '';
        if (currentAction.startsWith('$(') || !currentAction) {
            form.setAttribute('action', linkLoginOnly);
        }
        form.submit();
    });

    return false;
}

function autoConnectHotspot(voucherCode, password) {
    cancelAllPendingTimers();
    const codeInput = document.getElementById('voucherCodeInput');
    const passInput = document.getElementById('voucherPassInput');
    const form = document.getElementById('voucherLoginForm');
    const payBtn = document.getElementById('payButton');
    const btnText = document.getElementById('btnText');
    const statusDiv = document.getElementById('statusMessage');

    if (payBtn) payBtn.disabled = false;
    if (btnText) btnText.innerText = '✅ Connected! (' + voucherCode + ')';

    if (statusDiv) {
        statusDiv.className = 'status-msg status-success';
        statusDiv.style.display = 'block';
        statusDiv.innerHTML =
            '<div style="font-weight:800;font-size:15px;margin-bottom:4px;">✅ Payment Confirmed — You Are Connected!</div>' +
            '<div style="font-size:12.5px;margin-bottom:8px;">M-Pesa Voucher: <strong>' + voucherCode + '</strong>. Signing your device in now...</div>' +
            '<button type="button" onclick="document.getElementById(\'voucherLoginForm\').submit()" style="background:#15803d;color:#fff;border:none;padding:8px 14px;border-radius:8px;font-weight:700;font-size:12.5px;cursor:pointer;">Continue to Internet &rarr;</button>';
    }

    if (codeInput && form) {
        codeInput.value = voucherCode;
        if (passInput) passInput.value = password || voucherCode;
        try {
            sessionStorage.setItem('hsmart_last_connected_user', voucherCode);
            sessionStorage.setItem('hsmart_last_connected_time', Date.now());
        } catch(e) {}
        const currentAction = form.getAttribute('action') || '';
        if (currentAction.startsWith('$(') || !currentAction) {
            form.setAttribute('action', linkLoginOnly);
        }
        autoConnectTimer = setTimeout(() => { form.submit(); }, 1500);
    }
}

function startPaymentPolling(checkoutId, merchantRef, phone) {
    if (stkPollTimer) clearInterval(stkPollTimer);
    let attempts = 0;
    const payBtn = document.getElementById('payButton');
    const btnText = document.getElementById('btnText');

    stkPollTimer = setInterval(() => {
        attempts++;
        if (attempts > 48) {
            clearInterval(stkPollTimer);
            if (payBtn) payBtn.disabled = false;
            if (btnText) btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
            showStatus('Still waiting for M-Pesa confirmation. If you already paid, enter your M-Pesa Receipt Code in the Voucher box below to connect instantly.', 'error');
            return;
        }
        const q = new URLSearchParams({
            checkout_id: checkoutId || '',
            reference: merchantRef || checkoutId || '',
            phone: phone || '',
            tenant_id: '{$tenantId}',
            router_id: '{$routerId}'
        });
        fetch(apiBase + '/api/payment/status?' + q.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res) return;
            if (res.status === 'completed' && res.voucher_code) {
                clearInterval(stkPollTimer);
                autoConnectHotspot(res.voucher_code, res.password || res.voucher_code);
            } else if (res.status === 'failed') {
                clearInterval(stkPollTimer);
                if (payBtn) payBtn.disabled = false;
                if (btnText) btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
                showStatus('❌ ' + (res.message || 'Payment was cancelled or failed. Please try again.'), 'error');
            }
        })
        .catch(() => {});
    }, 2500);
}

function handleMpesaPayment(e) {
    if (e && e.preventDefault) e.preventDefault();
    cancelAllPendingTimers();
    const phoneInput = document.getElementById('phoneInput');
    const rawPhone = phoneInput ? phoneInput.value.trim() : '';
    const normalized = normalizeKenyanPhone(rawPhone);
    const payBtn = document.getElementById('payButton');
    const btnText = document.getElementById('btnText');

    if (!normalized) {
        showStatus('Please enter a valid Kenyan Safaricom phone number (e.g. 0712345678).', 'error');
        return;
    }

    // Lock button with spinner
    payBtn.disabled = true;
    btnText.innerText = 'Sending STK Push to ' + rawPhone + '...';
    showStatus('⏳ Sending M-Pesa STK Push. Please check your phone...', 'success');

    const payload = {
        plan_id: selectedPlanId,
        package_id: selectedPlanId,
        phone: normalized,
        amount: String(selectedPlanPrice).replace(/,/g, ''),
        tenant_id: '{$tenantId}',
        router_id: '{$routerId}',
        mac: clientMac,
        ip: clientIp
    };

    fetch(apiBase + '/api/payment/stk-push', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data && (data.success === true || data.status === 'success' || data.ResponseCode === '0')) {
            const checkoutId = data.checkout_request_id || '';
            const merchantRef = data.reference || data.external_reference || checkoutId;
            btnText.innerText = '⏳ Waiting for M-Pesa PIN...';
            showStatus('📱 STK Push sent! Enter your M-Pesa PIN on your phone. Waiting for confirmation to connect you automatically...', 'success');
            startPaymentPolling(checkoutId, merchantRef, normalized);
        } else {
            payBtn.disabled = false;
            btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
            showStatus('❌ ' + (data.message || data.CustomerMessage || 'Could not initiate M-Pesa STK Push. Please try again.'), 'error');
        }
    })
    .catch(err => {
        payBtn.disabled = false;
        btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
        showStatus('⚠️ Network error reaching payment server. Please retry.', 'error');
    });
}

function showStatus(msg, type) {
    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerText = msg;
    statusDiv.className = 'status-msg status-' + type;
    statusDiv.style.display = 'block';
}

function openTvModal() {
    cancelAllPendingTimers();
    const modal = document.getElementById('tvModal');
    if (modal) {
        modal.style.display = 'flex';
        const mainPhone = document.getElementById('phoneInput');
        const tvPhone = document.getElementById('tvPhoneInput');
        if (mainPhone && tvPhone && mainPhone.value.trim() && !tvPhone.value.trim()) {
            tvPhone.value = mainPhone.value.trim();
        }
        const select = document.getElementById('tvPackageSelect');
        if (select && selectedPlanId) {
            select.value = selectedPlanId;
            updateTvSelectedPackage();
        }
        loadNetworkDevices();
    }
}

function closeTvModal() {
    if (tvPollTimer) {
        clearInterval(tvPollTimer);
        tvPollTimer = null;
    }
    const modal = document.getElementById('tvModal');
    if (modal) modal.style.display = 'none';
    const status = document.getElementById('tvStatusMsg');
    if (status) status.style.display = 'none';
}

function handleTvMacInput(input) {
    let val = input.value.replace(/[^a-fA-F0-9]/g, '').toUpperCase();
    let formatted = '';
    for (let i = 0; i < val.length && i < 12; i++) {
        if (i > 0 && i % 2 === 0) formatted += ':';
        formatted += val[i];
    }
    input.value = formatted;
    filterNetworkDevices(formatted);
}

function updateTvSelectedPackage() {
    const select = document.getElementById('tvPackageSelect');
    const btnText = document.getElementById('tvBtnText');
    if (select && select.selectedIndex > 0) {
        const opt = select.options[select.selectedIndex];
        tvSelectedPackageId = opt.value;
        tvSelectedPackagePrice = opt.getAttribute('data-price') || '0';
        if (btnText) btnText.innerText = 'Bind & Pay KES ' + tvSelectedPackagePrice;
    } else {
        tvSelectedPackageId = '';
        tvSelectedPackagePrice = '0';
        if (btnText) btnText.innerText = 'Bind & Pay';
    }
}

function loadNetworkDevices(force) {
    const placeholder = document.getElementById('devicesListPlaceholder');
    const itemsContainer = document.getElementById('devicesListItems');
    if (!placeholder || !itemsContainer) return;

    if (!force && networkDevicesCache.length > 0) {
        renderNetworkDevices(networkDevicesCache);
        return;
    }

    placeholder.style.display = 'block';
    placeholder.innerText = 'Looking for devices on this network...';
    itemsContainer.style.display = 'none';

    const q = new URLSearchParams({
        router_id: '{$routerId}',
        tenant_id: '{$tenantId}',
        mac: clientMac
    });

    fetch(apiBase + '/api/portal/network-devices?' + q.toString(), {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.success && Array.isArray(data.devices)) {
            networkDevicesCache = data.devices;
            renderNetworkDevices(data.devices);
        } else {
            placeholder.innerText = 'No devices found yet. Make sure your TV is connected to this Wi-Fi.';
        }
    })
    .catch(() => {
        placeholder.innerText = 'Enter your TV MAC address above if not listed.';
    });
}

function renderNetworkDevices(devices) {
    const placeholder = document.getElementById('devicesListPlaceholder');
    const itemsContainer = document.getElementById('devicesListItems');
    if (!placeholder || !itemsContainer) return;

    const filtered = devices.filter(d => !d.is_current);
    const listToRender = filtered.length > 0 ? filtered : devices;

    if (listToRender.length === 0) {
        placeholder.style.display = 'block';
        placeholder.innerText = 'No unauthorized devices detected. Ensure your TV is connected to Wi-Fi.';
        itemsContainer.style.display = 'none';
        return;
    }

    placeholder.style.display = 'none';
    itemsContainer.innerHTML = '';
    itemsContainer.style.display = 'flex';

    listToRender.forEach(d => {
        const row = document.createElement('div');
        row.style.cssText = 'padding:6px 8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-size:12px; transition:background 0.15s;';
        row.onmouseover = () => { row.style.background = '#f1f5f9'; };
        row.onmouseout = () => { row.style.background = '#fff'; };
        row.onclick = () => selectNetworkDevice(d.mac, d.name, d.raw_name);

        const isTv = d.name.toLowerCase().includes('tv') || d.name.toLowerCase().includes('bravia') || d.name.toLowerCase().includes('roku') || d.name.toLowerCase().includes('box');
        const icon = isTv ? '📺' : '📱';

        row.innerHTML =
            '<div style="display:flex; align-items:center; gap:6px; overflow:hidden;">' +
                '<span style="font-size:14px;">' + icon + '</span>' +
                '<div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' +
                    '<div style="font-weight:700; color:#1e293b; font-size:12px;">' + d.name + '</div>' +
                    '<div style="font-family:monospace; color:#64748b; font-size:11px;">' + d.mac + '</div>' +
                '</div>' +
            '</div>' +
            '<button type="button" style="background:#e0f2fe; color:#0369a1; border:none; border-radius:4px; padding:2px 8px; font-size:11px; font-weight:700; cursor:pointer;">Select</button>';
        itemsContainer.appendChild(row);
    });
}

function filterNetworkDevices(query) {
    if (!networkDevicesCache.length) return;
    const cleanQ = query.toUpperCase();
    const matched = networkDevicesCache.filter(d => d.mac.toUpperCase().includes(cleanQ) || (d.name && d.name.toUpperCase().includes(cleanQ)));
    renderNetworkDevices(matched);
}

function selectNetworkDevice(mac, name, rawName) {
    const macInput = document.getElementById('tvMacInput');
    const nameInput = document.getElementById('tvNameInput');
    if (macInput) macInput.value = mac;
    if (nameInput && !nameInput.value.trim()) {
        nameInput.value = rawName || name.replace(/\s*\([0-9.]+\)/, '');
    }
}

function showTvStatus(msg, type) {
    const el = document.getElementById('tvStatusMsg');
    if (!el) return;
    el.style.display = 'block';
    if (type === 'success') {
        el.style.background = '#dcfce7';
        el.style.border = '1px solid #86efac';
        el.style.color = '#15803d';
    } else {
        el.style.background = '#fee2e2';
        el.style.border = '1px solid #fca5a5';
        el.style.color = '#b91c1c';
    }
    el.innerHTML = msg;
}

function submitTvBindPayment() {
    const macInput = document.getElementById('tvMacInput');
    const phoneInput = document.getElementById('tvPhoneInput');
    const nameInput = document.getElementById('tvNameInput');
    const bindBtn = document.getElementById('tvBindBtn');
    const btnText = document.getElementById('tvBtnText');

    const rawMac = macInput ? macInput.value.trim().toUpperCase() : '';
    const rawPhone = phoneInput ? phoneInput.value.trim() : '';
    const deviceName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : 'Smart TV';

    const macRegex = /^([0-9A-F]{2}:){5}[0-9A-F]{2}$/;
    if (!macRegex.test(rawMac)) {
        showTvStatus('Please enter a valid 12-character MAC Address (e.g. AA:BB:CC:DD:EE:FF).', 'error');
        if (macInput) macInput.focus();
        return;
    }

    if (!tvSelectedPackageId) {
        showTvStatus('Please select a package from the dropdown list.', 'error');
        return;
    }

    const normalizedPhone = normalizeKenyanPhone(rawPhone);
    if (!normalizedPhone) {
        showTvStatus('Please enter a valid M-Pesa phone number (e.g. 07XXXXXXXX or 01XXXXXXXX).', 'error');
        if (phoneInput) phoneInput.focus();
        return;
    }

    if (bindBtn) bindBtn.disabled = true;
    if (btnText) btnText.innerText = '⏳ Sending M-Pesa STK...';
    showTvStatus('Sending M-Pesa prompt to ' + normalizedPhone + '...', 'success');

    const payload = {
        phone: normalizedPhone,
        amount: String(tvSelectedPackagePrice).replace(/,/g, ''),
        package_id: tvSelectedPackageId,
        plan_id: tvSelectedPackageId,
        router_id: '{$routerId}',
        tenant_id: '{$tenantId}',
        is_tv_bind: 1,
        target_mac: rawMac,
        device_name: deviceName,
        mac: clientMac,
        ip: clientIp
    };

    fetch(apiBase + '/api/payment/stk-push', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res && res.success) {
            showTvStatus('📲 <strong>M-Pesa STK Prompt sent!</strong> Please enter your PIN on ' + normalizedPhone + ' to connect your TV.', 'success');
            if (btnText) btnText.innerText = '⏳ Waiting for M-Pesa PIN...';
            startTvPaymentPolling(res.checkout_request_id || res.reference, res.reference || res.checkout_request_id, normalizedPhone, rawMac, deviceName);
        } else {
            if (bindBtn) bindBtn.disabled = false;
            if (btnText) btnText.innerText = 'Bind & Pay KES ' + tvSelectedPackagePrice;
            showTvStatus('❌ ' + (res.message || 'Failed to initiate M-Pesa payment. Please try again.'), 'error');
        }
    })
    .catch(err => {
        if (bindBtn) bindBtn.disabled = false;
        if (btnText) btnText.innerText = 'Bind & Pay KES ' + tvSelectedPackagePrice;
        showTvStatus('❌ Network error connecting to payment gateway. Please try again.', 'error');
    });
}

function startTvPaymentPolling(checkoutId, merchantRef, phone, targetMac, deviceName) {
    if (tvPollTimer) clearInterval(tvPollTimer);
    let attempts = 0;
    const bindBtn = document.getElementById('tvBindBtn');
    const btnText = document.getElementById('tvBtnText');

    tvPollTimer = setInterval(() => {
        attempts++;
        if (attempts > 48) {
            clearInterval(tvPollTimer);
            if (bindBtn) bindBtn.disabled = false;
            if (btnText) btnText.innerText = 'Bind & Pay KES ' + tvSelectedPackagePrice;
            showTvStatus('Still waiting for payment confirmation. If already deducted, your TV will connect automatically in a moment.', 'error');
            return;
        }

        const q = new URLSearchParams({
            checkout_id: checkoutId || '',
            reference: merchantRef || checkoutId || '',
            phone: phone || '',
            tenant_id: '{$tenantId}',
            router_id: '{$routerId}'
        });

        fetch(apiBase + '/api/payment/status?' + q.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res) return;
            if (res.status === 'completed') {
                clearInterval(tvPollTimer);
                if (btnText) btnText.innerText = '✅ TV Connected!';
                showTvStatus('🎉 <strong>TV Connected Successfully!</strong><br>' + deviceName + ' (' + targetMac + ') has been bound. Your TV can now stream freely without entering any password!', 'success');
                setTimeout(() => {
                    closeTvModal();
                    showStatus('✅ TV (' + targetMac + ') successfully connected and authorized for streaming!', 'success');
                }, 4000);
            } else if (res.status === 'failed') {
                clearInterval(tvPollTimer);
                if (bindBtn) bindBtn.disabled = false;
                if (btnText) btnText.innerText = 'Bind & Pay KES ' + tvSelectedPackagePrice;
                showTvStatus('❌ ' + (res.message || 'Payment cancelled or declined.'), 'error');
            }
        })
        .catch(() => {});
    }, 2500);
}

// Initial label, phone restore & error check on load
document.addEventListener('DOMContentLoaded', () => {
    const btnText = document.getElementById('btnText');
    if (btnText && selectedPlanPrice) {
        btnText.innerText = 'Pay KES ' + selectedPlanPrice + ' via M-Pesa';
    }

    // Preserve phone input & cancel pending auto-timers when user is interacting
    const phoneInput = document.getElementById('phoneInput');
    if (phoneInput) {
        try {
            const savedPhone = sessionStorage.getItem('hsmart_typed_phone');
            if (savedPhone && !phoneInput.value) {
                phoneInput.value = savedPhone;
            }
        } catch(e) {}
        phoneInput.addEventListener('focus', cancelAllPendingTimers);
        phoneInput.addEventListener('input', () => {
            cancelAllPendingTimers();
            try { sessionStorage.setItem('hsmart_typed_phone', phoneInput.value); } catch(e) {}
        });
    }

    // Only display login error if router returned a real error
    const attemptedUser = urlParams.get('username') || urlParams.get('user') || urlParams.get('voucher') || urlParams.get('code');
    if (mtError && mtError !== '') {
        cancelAllPendingTimers();
        sessionStorage.removeItem('hsmart_last_connected_user');
        sessionStorage.removeItem('hsmart_last_auto_submit');

        if (mtError.toLowerCase().includes('invalid username') || (mtError.toLowerCase().includes('user') && mtError.toLowerCase().includes('not found'))) {
            if (attemptedUser) {
                showStatus('⚠️ Invalid or expired voucher (' + attemptedUser + '). Please select a package above to purchase access.', 'error');
            }
        } else {
            showStatus('⚠️ ' + mtError, 'error');
        }
    }

    const form = document.getElementById('voucherLoginForm');
    if (form) {
        const act = form.getAttribute('action') || '';
        if (act.startsWith('$(')) {
            form.setAttribute('action', linkLoginOnly);
        }
    }

    // Auto-reconnect returning users via MAC session lookup ONLY if no error and user hasn't typed a phone number
    if (clientMac && (!mtError || mtError === '')) {
        fetch(apiBase + '/api/portal/check-session?mac=' + encodeURIComponent(clientMac) + '&tenant_id={$tenantId}&router_id={$routerId}', {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.status === 'active') {
                const pIn = document.getElementById('phoneInput');
                if (!pIn || !pIn.value.trim()) {
                    showStatus('Welcome back! Reconnecting your device...', 'success');
                    autoConnectHotspot(res.username || res.voucher_code, res.password || res.username || res.voucher_code);
                }
            }
        })
        .catch(() => {});
    }

    // Auto-refresh when tab becomes visible after payment on mobile
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && clientMac && (!mtError || mtError === '')) {
            fetch(apiBase + '/api/portal/check-session?mac=' + encodeURIComponent(clientMac) + '&tenant_id={$tenantId}&router_id={$routerId}', {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                if (res && res.status === 'active') {
                    const pIn = document.getElementById('phoneInput');
                    if (!pIn || !pIn.value.trim()) {
                        showStatus('Payment confirmed! Reconnecting your device...', 'success');
                        autoConnectHotspot(res.username || res.voucher_code, res.password || res.username || res.voucher_code);
                    }
                }
            })
            .catch(() => {});
        }
    });
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

    /**
     * Resolve tenant settings and packages for a given Router and render the specified Hotspot file.
     */
    public static function renderForRouter(\App\Models\Router $router, string $filename = 'login.html'): string
    {
        $files = self::generateRouterPortalFiles($router);
        $clean = strtolower(basename($filename));
        return $files[$clean] ?? $files['login.html'];
    }

    /**
     * Generate the complete suite of MikroTik Hotspot HTML files isolated to the router's tenant.
     */
    public static function generateRouterPortalFiles(\App\Models\Router $router): array
    {
        $tenantId = (int) ($router->tenant_id ?: 1);
        $routerId = (int) $router->id;

        $settings = [
            'tenant_id'            => $tenantId,
            'router_id'            => $routerId,
            'brand_name'           => 'HSmart WiFi',
            'headline'             => 'Welcome to High-Speed Internet',
            'subheadline'          => 'Instant automated M-Pesa activation with zero buffering.',
            'brand_color'          => '#ff6600',
            'secondary_color'      => '#0f172a',
            'support_phone'        => '+254 743 606 108',
            'support_whatsapp'     => '254743606108',
            'payment_instructions' => 'Select a package, enter your M-Pesa phone number, and accept the STK push on your phone.',
            'terms_text'           => 'By connecting, you agree to our fair use policy and terms of service.',
            'logo_url'             => 'https://isp.hsmart.co.ke/images/hsmart-isp-logo.svg',
            'background_url'       => '',
        ];
        $template = 'midnight';

        try {
            $tenantObj = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenantObj && !empty($tenantObj->name)) {
                $settings['brand_name'] = $tenantObj->name;
            }
            if ($tenantObj && !empty($tenantObj->phone)) {
                $settings['support_phone'] = $tenantObj->phone;
                $settings['support_whatsapp'] = preg_replace('/\D/', '', $tenantObj->phone);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                $saved = \Illuminate\Support\Facades\DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->first();
                if ($saved) {
                    $template = $saved->template ?? ($saved->selected_template ?? 'midnight');
                    if (!empty($saved->brand_name)) $settings['brand_name'] = $saved->brand_name;
                    if (!empty($saved->headline)) $settings['headline'] = $saved->headline;
                    if (!empty($saved->subheadline)) $settings['subheadline'] = $saved->subheadline;
                    if (!empty($saved->brand_color)) $settings['brand_color'] = $saved->brand_color;
                    if (!empty($saved->secondary_color)) $settings['secondary_color'] = $saved->secondary_color;
                    if (!empty($saved->support_phone)) $settings['support_phone'] = $saved->support_phone;
                    if (!empty($saved->support_whatsapp)) $settings['support_whatsapp'] = $saved->support_whatsapp;
                    if (!empty($saved->payment_instructions)) $settings['payment_instructions'] = $saved->payment_instructions;
                    if (!empty($saved->terms_text)) $settings['terms_text'] = $saved->terms_text;
                    if (!empty($saved->logo_url)) $settings['logo_url'] = $saved->logo_url;
                    if (!empty($saved->background_url)) $settings['background_url'] = $saved->background_url;
                }
            }
        } catch (\Throwable $e) {}

        $plans = [];
        try {
            $plansQuery = \App\Models\PackageList::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('type', 'hotspot')->orWhereNull('type');
                })
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                });

            if ($routerId) {
                $plansQuery->where(function ($q) use ($routerId) {
                    $q->whereHas('routers', function ($rq) use ($routerId) {
                        $rq->where('routers.id', $routerId);
                    })->orWhereDoesntHave('routers');
                });
            }

            $plans = $plansQuery->orderBy('price', 'asc')->take(8)->get()->toArray();

            if (empty($plans)) {
                $plans = \App\Models\PackageList::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) {
                        $q->where('is_active', true)->orWhereNull('is_active');
                    })
                    ->orderBy('price', 'asc')
                    ->take(6)
                    ->get()
                    ->toArray();
            }
        } catch (\Throwable $e) {}

        $loginHtml = self::render($template, $settings, $plans);
        $brandEsc  = htmlspecialchars($settings['brand_name'], ENT_QUOTES, 'UTF-8');

        $rloginHtml = <<<HTML
$(if http-status == 302)Hotspot login required$(endif)
$(if http-header == "Location")$(link-redirect)$(endif)
<!DOCTYPE html>
<html>
<head>
<title>{$brandEsc} - Redirecting...</title>
<meta http-equiv="refresh" content="0; url=$(link-redirect)">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
</head>
<body>
<script>window.location.href = "$(link-redirect)";</script>
</body>
</html>
HTML;

        $redirectHtml = <<<HTML
$(if http-status == 302)Hotspot redirect$(endif)
$(if http-header == "Location")$(link-redirect)$(endif)
<!DOCTYPE html>
<html>
<head>
<title>{$brandEsc}</title>
<meta http-equiv="refresh" content="0; url=$(link-redirect)">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
</head>
<body>
<script>window.location.href = "$(link-redirect)";</script>
</body>
</html>
HTML;

        $aloginHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brandEsc} - Connected</title>
<meta http-equiv="refresh" content="2; url=$(link-redirect)">
<style>
body { background: #0b0f19; color: #fff; font-family: -apple-system, BlinkMacSystemFont, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 20px; }
.box { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 28px 24px; max-width: 380px; width: 100%; }
.ok { width: 52px; height: 52px; border-radius: 50%; background: #10b981; color: #fff; font-size: 26px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; }
h2 { margin: 0 0 8px; font-size: 20px; }
p { color: #94a3b8; font-size: 13px; margin: 0 0 18px; }
a { display: inline-block; background: #ff6600; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <div class="ok">✓</div>
    <h2>You Are Connected!</h2>
    <p>Welcome to {$brandEsc}. Redirecting to the internet...</p>
    <a href="$(link-redirect)">Continue Browsing &rarr;</a>
</div>
<script>setTimeout(function(){ window.location.href = "$(link-redirect)"; }, 1500);</script>
</body>
</html>
HTML;

        $statusHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brandEsc} - Session Status</title>
<style>
body { background: #0b0f19; color: #fff; font-family: -apple-system, BlinkMacSystemFont, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
.box { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 26px 22px; max-width: 400px; width: 100%; }
h2 { margin: 0 0 14px; font-size: 19px; text-align: center; color: #10b981; }
.row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #334155; font-size: 13px; }
.row span { color: #94a3b8; }
.btn { display: block; width: 100%; text-align: center; background: #ef4444; color: #fff; text-decoration: none; padding: 12px; border-radius: 10px; font-weight: 700; margin-top: 18px; border: none; cursor: pointer; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <h2>🟢 Connected to {$brandEsc}</h2>
    <div class="row"><span>User / Voucher</span><strong>$(username)</strong></div>
    <div class="row"><span>IP Address</span><strong>$(ip)</strong></div>
    <div class="row"><span>MAC Address</span><strong>$(mac)</strong></div>
    <div class="row"><span>Connected Time</span><strong>$(uptime)</strong></div>
    <div class="row"><span>Data Used</span><strong>$(bytes-in-nice) / $(bytes-out-nice)</strong></div>
    $(if session-time-left)
    <div class="row"><span>Time Remaining</span><strong style="color:#10b981;">$(session-time-left)</strong></div>
    $(endif)
    $(if remain-bytes-total-nice)
    <div class="row"><span>Data Remaining</span><strong style="color:#10b981;">$(remain-bytes-total-nice)</strong></div>
    $(endif)
    <form action="$(link-logout)" name="logout" method="post">
        <button type="submit" class="btn">Disconnect Session</button>
    </form>
</div>
</body>
</html>
HTML;

        $logoutHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brandEsc} - Disconnected</title>
<style>
body { background: #0b0f19; color: #fff; font-family: -apple-system, BlinkMacSystemFont, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 20px; }
.box { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 28px 24px; max-width: 380px; width: 100%; }
h2 { margin: 0 0 8px; font-size: 20px; }
p { color: #94a3b8; font-size: 13px; margin: 0 0 18px; }
a { display: inline-block; background: #ff6600; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <h2>Session Disconnected</h2>
    <p>You have disconnected from {$brandEsc}.</p>
    <a href="$(link-login)">Reconnect to WiFi &rarr;</a>
</div>
</body>
</html>
HTML;

        $floginHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$brandEsc} - Login</title>
<meta http-equiv="refresh" content="0; url=$(link-login-only)?error=$(error-esc)&mac=$(mac-esc)&ip=$(ip)">
</head>
<body>
<script>window.location.href = "$(link-login-only)?error=$(error-esc)&mac=$(mac-esc)&ip=$(ip)";</script>
</body>
</html>
HTML;

        return [
            'login.html'    => $loginHtml,
            'flogin.html'   => $floginHtml,
            'rlogin.html'   => $rloginHtml,
            'redirect.html' => $redirectHtml,
            'alogin.html'   => $aloginHtml,
            'status.html'   => $statusHtml,
            'logout.html'   => $logoutHtml,
            'error.html'    => $floginHtml,
        ];
    }

    /**
     * Push complete Hotspot network, built-in Wi-Fi bridge (for hAP models), Walled Garden (incl. ICMP for Tenda/APs),
     * and all Captive Portal templates onto a MikroTik router via API & /tool/fetch.
     */
    /**
     * Resilient password decryption supporting current and legacy APP_KEYs
     */
    public static function decryptRouterPassword(?string $encrypted): string
    {
        if (empty($encrypted)) return '';
        $keys = [
            'base64:OsvBUcJog9u3L7RHpAXOV3lAVumKIyCIOscxYwKXufQ=',
            'base64:NSLTh+nKIi546KF1WfENeIVjjBvu0SSvE2kgyiFu7CE=',
            'base64:motZaNhPAm6uv5uffwL6kbrJdtOu3qgrdgjtXarDoaU=',
            'base64:CGygtTkIhnvb+mwr8Mv5j5DjDkgmbq9ej3mmKwHcS5g=',
        ];
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            foreach ($keys as $k) {
                try {
                    $rawKey = base64_decode(substr($k, 7));
                    $enc = new \Illuminate\Encryption\Encrypter($rawKey, 'AES-256-CBC');
                    return $enc->decryptString($encrypted);
                } catch (\Throwable $e2) {}
            }
        }
        return $encrypted;
    }

    public static function pushAllToRouter(\App\Models\Router $router, array $extraWalledGarden = []): array
    {
        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = self::decryptRouterPassword($router->api_password ?: $router->password);

        if (!$host) {
            throw new \RuntimeException("Router {$router->name} has no IP or VPN IP configured.");
        }

        $api = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 15);

        $tenantId = (int) ($router->tenant_id ?: 1);
        $brandName = 'HSmart WiFi';
        try {
            $tenant = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant && !empty($tenant->name)) $brandName = $tenant->name;
            if (\Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                $ps = \Illuminate\Support\Facades\DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->first();
                if ($ps && !empty($ps->brand_name)) $brandName = $ps->brand_name;
            }
        } catch (\Throwable $e) {}

        $roles = is_array($router->port_roles) ? $router->port_roles : [];
        $wanPort = $router->wan_interface ?: ($roles['wan'] ?? 'ether1');
        $pppPort = $roles['pppoe'] ?? 'ether3';
        $configuredHsPort = $router->lan_interface ?: ($roles['hotspot'] ?? 'ether2');

        // 1. Detect interfaces & whether built-in wireless (wlan1/wlan2/wifi1/wifi2) exists (e.g. MikroTik hAP lite RB941-2nD)
        $ifaces = $api->executeCommand('/interface/print');
        $wlanNames = [];
        $etherHsPorts = [];
        $hasFlash = false;

        if (is_array($ifaces)) {
            foreach ($ifaces as $if) {
                $ifName = $if['name'] ?? '';
                $ifType = $if['type'] ?? '';
                if (in_array($ifType, ['wlan', 'wifi']) || str_starts_with($ifName, 'wlan') || str_starts_with($ifName, 'wifi')) {
                    $wlanNames[] = $ifName;
                }
                if ($ifName === $configuredHsPort && $ifName !== $wanPort && $ifName !== 'wg-hsmart') {
                    $etherHsPorts[] = $ifName;
                }
                if ($ifName === 'ether4' && $wanPort !== 'ether4' && $pppPort !== 'ether4') {
                    $etherHsPorts[] = 'ether4';
                }
            }
        }
        if (empty($etherHsPorts) && $configuredHsPort !== 'hs-bridge' && $configuredHsPort !== $wanPort) {
            $etherHsPorts[] = $configuredHsPort;
        }
        $etherHsPorts = array_values(array_unique($etherHsPorts));

        // Enable and configure built-in wireless AP on MikroTik hAP models
        if (!empty($wlanNames)) {
            try {
                // Ensure default security profile is open (none) for captive portal
                $secProfs = $api->executeCommand('/interface/wireless/security-profiles/print');
                if (is_array($secProfs)) {
                    foreach ($secProfs as $sp) {
                        if (($sp['name'] ?? '') === 'default') {
                            $api->executeCommand('/interface/wireless/security-profiles/set', [
                                'mode' => 'none',
                            ], ['.id' => $sp['.id']]);
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            try {
                $wlans = $api->executeCommand('/interface/wireless/print');
                if (is_array($wlans)) {
                    foreach ($wlans as $idx => $wl) {
                        $ssid = count($wlanNames) > 1 && $idx > 0 ? "{$brandName} 5G" : $brandName;
                        try {
                            $api->executeCommand('/interface/wireless/set', [
                                'mode'             => 'ap-bridge',
                                'ssid'             => $ssid,
                                'security-profile' => 'default',
                                'disabled'         => 'no',
                                'default-forwarding' => 'yes',
                            ], ['.id' => $wl['.id']]);
                        } catch (\Throwable $we) {
                            // Fallback if ap-bridge license level restricted
                            try {
                                $api->executeCommand('/interface/wireless/set', [
                                    'ssid'     => $ssid,
                                    'disabled' => 'no',
                                ], ['.id' => $wl['.id']]);
                            } catch (\Throwable $we2) {}
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Create/Use Hotspot Bridge ('hs-bridge') bridging ether2 + ether4 + wlan1 so BOTH wired APs (Tenda) and built-in Wi-Fi serve Hotspot!
        $targetHsInterface = $configuredHsPort;
        try {
            $bridges = $api->executeCommand('/interface/bridge/print');
            $hasHsBridge = false;
            if (is_array($bridges)) {
                foreach ($bridges as $br) {
                    if (($br['name'] ?? '') === 'hs-bridge') {
                        $hasHsBridge = true;
                        if (($br['disabled'] ?? 'false') === 'true') {
                            $api->executeCommand('/interface/bridge/set', ['disabled' => 'no'], ['.id' => $br['.id']]);
                        }
                        break;
                    }
                }
            }
            if (!$hasHsBridge) {
                $api->executeCommand('/interface/bridge/add', [
                    'name'    => 'hs-bridge',
                    'comment' => 'hsmart-hotspot-bridge',
                ]);
            }

            // Ensure hs-bridge is a member of LAN interface-list so factory defconf firewall never blocks Hotspot clients
            try {
                $lists = $api->executeCommand('/interface/list/print');
                $hasLanList = false;
                if (is_array($lists)) {
                    foreach ($lists as $l) {
                        if (($l['name'] ?? '') === 'LAN') {
                            $hasLanList = true;
                            break;
                        }
                    }
                }
                if ($hasLanList) {
                    $members = $api->executeCommand('/interface/list/member/print');
                    $alreadyLan = false;
                    if (is_array($members)) {
                        foreach ($members as $m) {
                            if (($m['list'] ?? '') === 'LAN' && ($m['interface'] ?? '') === 'hs-bridge') {
                                $alreadyLan = true;
                                break;
                            }
                        }
                    }
                    if (!$alreadyLan) {
                        $api->executeCommand('/interface/list/member/add', [
                            'list'      => 'LAN',
                            'interface' => 'hs-bridge',
                        ]);
                    }
                }
            } catch (\Throwable $le) {}

            $bridgePorts = $api->executeCommand('/interface/bridge/port/print');
            $existingBridgeMap = [];
            if (is_array($bridgePorts)) {
                foreach ($bridgePorts as $bp) {
                    $existingBridgeMap[$bp['interface'] ?? ''] = $bp;
                }
            }

            $portsToBridge = array_unique(array_merge($etherHsPorts, $wlanNames));
            foreach ($portsToBridge as $pName) {
                if (!$pName || $pName === $wanPort || $pName === $pppPort || $pName === 'wg-hsmart' || $pName === 'hs-bridge') {
                    continue;
                }
                // Ensure physical port is enabled
                if (is_array($ifaces)) {
                    foreach ($ifaces as $if) {
                        if (($if['name'] ?? '') === $pName && ($if['disabled'] ?? 'false') === 'true') {
                            try {
                                $api->executeCommand('/interface/set', ['disabled' => 'no'], ['.id' => $if['.id']]);
                            } catch (\Throwable $e) {}
                        }
                    }
                }
                if (isset($existingBridgeMap[$pName])) {
                    $bp = $existingBridgeMap[$pName];
                    if (($bp['bridge'] ?? '') !== 'hs-bridge' || ($bp['disabled'] ?? 'false') === 'true') {
                        try {
                            $api->executeCommand('/interface/bridge/port/set', [
                                'bridge'   => 'hs-bridge',
                                'disabled' => 'no',
                            ], ['.id' => $bp['.id']]);
                        } catch (\Throwable $e) {}
                    }
                } else {
                    try {
                        $api->executeCommand('/interface/bridge/port/add', [
                            'bridge'    => 'hs-bridge',
                            'interface' => $pName,
                        ]);
                    } catch (\Throwable $e) {}
                }
            }

            $targetHsInterface = 'hs-bridge';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Bridge setup fallback on {$router->name}: " . $e->getMessage());
        }

        // 3. IP Address (10.5.50.1/24), IP Pool (hs-pool-10), DHCP Server (hs-dhcp-10) on $targetHsInterface
        try {
            $addrs = $api->executeCommand('/ip/address/print');
            $foundHsAddr = false;
            if (is_array($addrs)) {
                foreach ($addrs as $ad) {
                    if (str_contains($ad['address'] ?? '', '10.5.50.') || ($ad['comment'] ?? '') === 'hsmart-hotspot') {
                        $api->executeCommand('/ip/address/set', [
                            'address'   => '10.5.50.1/24',
                            'interface' => $targetHsInterface,
                            'disabled'  => 'no',
                        ], ['.id' => $ad['.id']]);
                        $foundHsAddr = true;
                        break;
                    }
                }
            }
            if (!$foundHsAddr) {
                $api->executeCommand('/ip/address/add', [
                    'address'   => '10.5.50.1/24',
                    'interface' => $targetHsInterface,
                    'comment'   => 'hsmart-hotspot',
                ]);
            }
        } catch (\Throwable $e) {}

        try {
            $pools = $api->executeCommand('/ip/pool/print');
            $hasPool = false;
            if (is_array($pools)) {
                foreach ($pools as $p) {
                    if (($p['name'] ?? '') === 'hs-pool-10') {
                        $api->executeCommand('/ip/pool/set', ['ranges' => '10.5.50.10-10.5.50.254'], ['.id' => $p['.id']]);
                        $hasPool = true;
                        break;
                    }
                }
            }
            if (!$hasPool) {
                $api->executeCommand('/ip/pool/add', ['name' => 'hs-pool-10', 'ranges' => '10.5.50.10-10.5.50.254']);
            }
        } catch (\Throwable $e) {}

        try {
            $dnets = $api->executeCommand('/ip/dhcp-server/network/print');
            $hasDnet = false;
            if (is_array($dnets)) {
                foreach ($dnets as $dn) {
                    if (($dn['address'] ?? '') === '10.5.50.0/24') {
                        $api->executeCommand('/ip/dhcp-server/network/set', [
                            'gateway'    => '10.5.50.1',
                            'dns-server' => '10.5.50.1,8.8.8.8',
                        ], ['.id' => $dn['.id']]);
                        $hasDnet = true;
                        break;
                    }
                }
            }
            if (!$hasDnet) {
                $api->executeCommand('/ip/dhcp-server/network/add', [
                    'address'    => '10.5.50.0/24',
                    'gateway'    => '10.5.50.1',
                    'dns-server' => '10.5.50.1,8.8.8.8',
                    'comment'    => 'hsmart-hotspot',
                ]);
            }

            $dhcps = $api->executeCommand('/ip/dhcp-server/print');
            $hasDhcp = false;
            if (is_array($dhcps)) {
                foreach ($dhcps as $d) {
                    if (($d['name'] ?? '') === 'hs-dhcp-10' || ($d['address-pool'] ?? '') === 'hs-pool-10' || in_array($d['interface'] ?? '', ['ether2', 'hs-bridge'])) {
                        $api->executeCommand('/ip/dhcp-server/set', [
                            'interface'    => $targetHsInterface,
                            'address-pool' => 'hs-pool-10',
                            'lease-time'   => '1h',
                            'disabled'     => 'no',
                        ], ['.id' => $d['.id']]);
                        $hasDhcp = true;
                        break;
                    }
                }
            }
            if (!$hasDhcp) {
                $api->executeCommand('/ip/dhcp-server/add', [
                    'name'         => 'hs-dhcp-10',
                    'interface'    => $targetHsInterface,
                    'address-pool' => 'hs-pool-10',
                    'lease-time'   => '1h',
                    'disabled'     => 'no',
                ]);
            }
        } catch (\Throwable $e) {}

        // 4. DNS & NAT Masquerade
        try {
            $api->executeCommand('/ip/dns/set', ['allow-remote-requests' => 'yes', 'servers' => '8.8.8.8,1.1.1.1']);
        } catch (\Throwable $e) {}

        try {
            $nats = $api->executeCommand('/ip/firewall/nat/print');
            $hasNat = false;
            if (is_array($nats)) {
                foreach ($nats as $n) {
                    if (($n['action'] ?? '') === 'masquerade' && (($n['src-address'] ?? '') === '10.5.50.0/24' || ($n['comment'] ?? '') === 'hsmart-hotspot-masq')) {
                        $hasNat = true;
                        break;
                    }
                }
            }
            if (!$hasNat) {
                $api->executeCommand('/ip/firewall/nat/add', [
                    'chain'       => 'srcnat',
                    'action'      => 'masquerade',
                    'src-address' => '10.5.50.0/24',
                    'comment'     => 'hsmart-hotspot-masq',
                ]);
            }
        } catch (\Throwable $e) {}

        // 5. Hotspot Profile & Server on $targetHsInterface
        try {
            $filesCheck = $api->executeCommand('/file/print');
            $htmlDir = 'hotspot';
            if (is_array($filesCheck)) {
                foreach ($filesCheck as $fc) {
                    if (($fc['name'] ?? '') === 'flash/hotspot' && ($fc['type'] ?? '') === 'directory') {
                        $hasFlash = true;
                    }
                }
            }

            $hsprofName = 'hsmart-hsprof';
            $profs = $api->executeCommand('/ip/hotspot/profile/print');
            $hasProf = false;
            if (is_array($profs)) {
                foreach ($profs as $pr) {
                    if (($pr['name'] ?? '') === $hsprofName) {
                        $api->executeCommand('/ip/hotspot/profile/set', [
                            'hotspot-address'   => '10.5.50.1',
                            'dns-name'          => 'hotspot.hsmart.lan',
                            'html-directory'    => $htmlDir,
                            'login-by'          => 'http-pap',
                            'keepalive-timeout' => '2m',
                        ], ['.id' => $pr['.id']]);
                        $hasProf = true;
                        break;
                    }
                }
            }
            if (!$hasProf) {
                $api->executeCommand('/ip/hotspot/profile/add', [
                    'name'              => $hsprofName,
                    'hotspot-address'   => '10.5.50.1',
                    'dns-name'          => 'hotspot.hsmart.lan',
                    'html-directory'    => $htmlDir,
                    'login-by'          => 'http-pap',
                    'keepalive-timeout' => '2m',
                ]);
            }

            $hss = $api->executeCommand('/ip/hotspot/print');
            $hasHs = false;
            if (is_array($hss)) {
                foreach ($hss as $h) {
                    if (($h['name'] ?? '') === 'hsmart-hotspot' || in_array($h['interface'] ?? '', ['ether2', 'hs-bridge', $configuredHsPort])) {
                        $api->executeCommand('/ip/hotspot/set', [
                            'interface'         => $targetHsInterface,
                            'profile'           => $hsprofName,
                            'address-pool'      => 'hs-pool-10',
                            'addresses-per-mac' => '10',
                            'disabled'          => 'no',
                        ], ['.id' => $h['.id']]);
                        $hasHs = true;
                        break;
                    }
                }
            }
            if (!$hasHs) {
                $api->executeCommand('/ip/hotspot/add', [
                    'name'              => 'hsmart-hotspot',
                    'interface'         => $targetHsInterface,
                    'profile'           => $hsprofName,
                    'address-pool'      => 'hs-pool-10',
                    'addresses-per-mac' => '10',
                    'disabled'          => 'no',
                ]);
            }
        } catch (\Throwable $e) {}

        // 6. Walled Garden Domains, Server IP, AND ICMP Ping Allowance (so Tenda/TP-Link APs pass WAN internet check!)
        try {
            $wgHosts = [
                '*.hsmart.co.ke',
                'isp.hsmart.co.ke',
                'pos.hsmart.co.ke',
                '*.payhero.co.ke',
                'api.payhero.co.ke',
                '*.safaricom.co.ke',
                'daraja.safaricom.co.ke',
                '*.kopokopo.com',
                'api.kopokopo.com',
                '*.paystack.co',
                'paystack.com',
                'api.paystack.co',
            ];
            if (!empty($extraWalledGarden)) {
                foreach ($extraWalledGarden as $dom) {
                    $cleanDom = trim((string) $dom);
                    if ($cleanDom !== '') {
                        $wgHosts[] = str_contains($cleanDom, '*') ? $cleanDom : "*{$cleanDom}*";
                    }
                }
            } else {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                        $psWg = \Illuminate\Support\Facades\DB::table('isp_portal_settings')->where('tenant_id', $tenantId)->value('walled_garden_domains');
                        $decodedWg = is_string($psWg) ? json_decode($psWg, true) : (is_array($psWg) ? $psWg : []);
                        if (is_array($decodedWg)) {
                            foreach ($decodedWg as $dom) {
                                $cleanDom = trim((string) $dom);
                                if ($cleanDom !== '') {
                                    $wgHosts[] = str_contains($cleanDom, '*') ? $cleanDom : "*{$cleanDom}*";
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {}
            }
            $wgHosts = array_values(array_unique($wgHosts));
            $existingWg = $api->executeCommand('/ip/hotspot/walled-garden/print');
            $existingHosts = [];
            if (is_array($existingWg)) {
                foreach ($existingWg as $w) {
                    if (!empty($w['dst-host'])) $existingHosts[] = strtolower($w['dst-host']);
                }
            }
            foreach ($wgHosts as $tgt) {
                if (!in_array(strtolower($tgt), $existingHosts)) {
                    $api->executeCommand('/ip/hotspot/walled-garden/add', ['dst-host' => $tgt, 'action' => 'allow']);
                }
            }

            $existingWgIp = $api->executeCommand('/ip/hotspot/walled-garden/ip/print');
            $hasServerIp = false;
            $hasIcmpAllow = false;
            if (is_array($existingWgIp)) {
                foreach ($existingWgIp as $wi) {
                    if (($wi['dst-address'] ?? '') === '213.199.49.216') {
                        $hasServerIp = true;
                    }
                    if (($wi['protocol'] ?? '') === 'icmp' && ($wi['action'] ?? '') === 'accept') {
                        $hasIcmpAllow = true;
                    }
                }
            }
            if (!$hasServerIp) {
                $api->executeCommand('/ip/hotspot/walled-garden/ip/add', [
                    'dst-address' => '213.199.49.216',
                    'action'      => 'accept',
                    'comment'     => 'hsmart-cloud-ip',
                ]);
            }
            if (!$hasIcmpAllow) {
                $api->executeCommand('/ip/hotspot/walled-garden/ip/add', [
                    'protocol' => 'icmp',
                    'action'   => 'accept',
                    'comment'  => 'hsmart-allow-ap-icmp-check',
                ]);
            }

            // Also add pre-hotspot ICMP accept in firewall filter so Tenda ping to 8.8.8.8 is never rejected by hs-unauth
            $filters = $api->executeCommand('/ip/firewall/filter/print');
            $hasPreHsIcmp = false;
            if (is_array($filters)) {
                foreach ($filters as $fl) {
                    if (($fl['comment'] ?? '') === 'hsmart-ap-icmp-allow') {
                        $hasPreHsIcmp = true;
                        break;
                    }
                }
            }
            if (!$hasPreHsIcmp) {
                try {
                    $api->executeCommand('/ip/firewall/filter/add', [
                        'chain'        => 'pre-hotspot',
                        'protocol'     => 'icmp',
                        'action'       => 'accept',
                        'comment'      => 'hsmart-ap-icmp-allow',
                    ]);
                } catch (\Throwable $fe) {}
            }
        } catch (\Throwable $e) {}

        // 7. Upload all Captive Portal HTML files into hotspot/ via /tool/fetch (over HTTP so no 4KB limit and overwrites cleanly!)
        $portalFiles = self::generateRouterPortalFiles($router);
        $uploadedFiles = [];

        // Remove old stale files first so RouterOS writes fresh copies
        try {
            $existingFiles = $api->executeCommand('/file/print');
            if (is_array($existingFiles)) {
                foreach ($existingFiles as $ef) {
                    $efName = $ef['name'] ?? '';
                    foreach (array_keys($portalFiles) as $pfKey) {
                        if ($efName === "hotspot/{$pfKey}" || $efName === "flash/hotspot/{$pfKey}") {
                            try {
                                $api->executeCommand('/file/remove', [], ['.id' => $ef['.id']]);
                            } catch (\Throwable $re) {}
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        foreach ($portalFiles as $fileName => $fileContent) {
            if (strlen($fileContent) < 3800) {
                // Fast direct API write for compact companion templates (< 3.8KB)
                try {
                    $api->executeCommand('/file/add', [
                        'name'     => "hotspot/{$fileName}",
                        'contents' => $fileContent,
                    ]);
                    $uploadedFiles[] = "hotspot/{$fileName}";
                    if ($hasFlash) {
                        $api->executeCommand('/file/add', [
                            'name'     => "flash/hotspot/{$fileName}",
                            'contents' => $fileContent,
                        ]);
                    }
                    continue;
                } catch (\Throwable $ae) {}
            }

            // Full-size HTML templates (> 4KB) via /tool/fetch over HTTP
            $cacheBust = time();
            $fetchUrl = "http://isp.hsmart.co.ke/provision/hotspot-portal/{$router->id}/{$fileName}?v={$cacheBust}";
            try {
                $fastApi = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 4);
                $fastApi->executeCommand('/tool/fetch', [
                    'url'      => $fetchUrl,
                    'dst-path' => "hotspot/{$fileName}",
                    'mode'     => 'http',
                ]);
                $uploadedFiles[] = "hotspot/{$fileName}";

                if ($hasFlash) {
                    $fastApi->executeCommand('/tool/fetch', [
                        'url'      => $fetchUrl,
                        'dst-path' => "flash/hotspot/{$fileName}",
                        'mode'     => 'http',
                    ]);
                }
            } catch (\Throwable $fe) {
                \Illuminate\Support\Facades\Log::warning("Portal file {$fileName} upload error on {$router->name}: " . $fe->getMessage());
            }
        }

        // Clear any unauthorized/stale hotspot host entries so connected APs/phones immediately re-trigger captive portal
        try {
            $hosts = $api->executeCommand('/ip/hotspot/host/print');
            if (is_array($hosts)) {
                foreach ($hosts as $h) {
                    if (($h['authorized'] ?? 'false') === 'false') {
                        try {
                            $api->executeCommand('/ip/hotspot/host/remove', [], ['.id' => $h['.id']]);
                        } catch (\Throwable $he) {}
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 8. Push Layer 3 On-Router Limit & Expiry Enforcer Script & Scheduler
        try {
            $scripts = $api->executeCommand('/system/script/print');
            $hasScript = false;
            if (is_array($scripts)) {
                foreach ($scripts as $sc) {
                    if (($sc['name'] ?? '') === 'hsmart_enforce_limits') {
                        $hasScript = true;
                        break;
                    }
                }
            }
            $scriptSource = ':do { /ip hotspot user { :foreach u in=[find] do={ :local lim [/ip hotspot user get $u limit-uptime]; :local up [/ip hotspot user get $u uptime]; :local uname [/ip hotspot user get $u name]; :if ($lim != "" and $lim != "0s" and $lim != "00:00:00") do={ :if ($up >= $lim) do={ /ip hotspot active remove [find user=$uname]; /ip hotspot cookie remove [find user=$uname]; /ip hotspot user remove $u; :log info ("[HSmart] Auto-kicked expired user: " . $uname); }; }; }; }; } on-error={};';
            if ($hasScript) {
                $api->executeCommand('/system/script/set', ['source' => $scriptSource], ['name' => 'hsmart_enforce_limits']);
            } else {
                $api->executeCommand('/system/script/add', [
                    'name'   => 'hsmart_enforce_limits',
                    'policy' => 'read,write,policy,test',
                    'source' => $scriptSource,
                ]);
            }

            $scheds = $api->executeCommand('/system/scheduler/print');
            $hasSched = false;
            if (is_array($scheds)) {
                foreach ($scheds as $sch) {
                    if (($sch['name'] ?? '') === 'hsmart_enforce_scheduler') {
                        $hasSched = true;
                        break;
                    }
                }
            }
            if (!$hasSched) {
                $api->executeCommand('/system/scheduler/add', [
                    'name'       => 'hsmart_enforce_scheduler',
                    'interval'   => '1m',
                    'on-event'   => 'hsmart_enforce_limits',
                    'start-time' => 'startup',
                ]);
            }
        } catch (\Throwable $e) {}

        return [
            'success'        => true,
            'interface'      => $targetHsInterface,
            'wlans_enabled'  => $wlanNames,
            'files_uploaded' => $uploadedFiles,
        ];
    }

    /**
     * Fast live sync: push updated Captive Portal HTML templates, walled-garden domains,
     * and clear unauthorized hotspot hosts so branding/design changes take effect immediately.
     */
    public static function pushPortalFilesOnly(\App\Models\Router $router, array $extraWalledGarden = []): array
    {
        $host = $router->vpn_ip ?: $router->ip;
        $port = (int) ($router->api_port ?: 8728);
        $user = $router->api_user ?: ($router->username ?: 'admin');
        $pass = self::decryptRouterPassword($router->api_password ?: $router->password);

        if (!$host) {
            throw new \RuntimeException("Router {$router->name} has no IP or VPN IP configured.");
        }

        $api = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 5);
        $portalFiles = self::generateRouterPortalFiles($router);
        $uploadedFiles = [];
        $hasFlash = false;

        // Detect flash/hotspot directory and remove stale HTML files so RouterOS overwrites cleanly
        try {
            $existingFiles = $api->executeCommand('/file/print');
            if (is_array($existingFiles)) {
                foreach ($existingFiles as $ef) {
                    $efName = $ef['name'] ?? '';
                    if ($efName === 'flash/hotspot' && ($ef['type'] ?? '') === 'directory') {
                        $hasFlash = true;
                    }
                }
                foreach ($existingFiles as $ef) {
                    $efName = $ef['name'] ?? '';
                    foreach (array_keys($portalFiles) as $pfKey) {
                        if ($efName === "hotspot/{$pfKey}" || $efName === "flash/hotspot/{$pfKey}") {
                            try {
                                $api->executeCommand('/file/remove', [], ['.id' => $ef['.id']]);
                            } catch (\Throwable $re) {}
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $cacheBust = time();
        foreach ($portalFiles as $fileName => $fileContent) {
            if (strlen($fileContent) < 3800) {
                try {
                    $api->executeCommand('/file/add', [
                        'name'     => "hotspot/{$fileName}",
                        'contents' => $fileContent,
                    ]);
                    $uploadedFiles[] = "hotspot/{$fileName}";
                    if ($hasFlash) {
                        $api->executeCommand('/file/add', [
                            'name'     => "flash/hotspot/{$fileName}",
                            'contents' => $fileContent,
                        ]);
                    }
                    continue;
                } catch (\Throwable $ae) {}
            }

            $fetchUrl = "http://isp.hsmart.co.ke/provision/hotspot-portal/{$router->id}/{$fileName}?v={$cacheBust}";
            try {
                $fastApi = new \App\Services\MikrotikApiService($host, $port, $user, $pass, 4);
                $fastApi->executeCommand('/tool/fetch', [
                    'url'      => $fetchUrl,
                    'dst-path' => "hotspot/{$fileName}",
                    'mode'     => 'http',
                ]);
                $uploadedFiles[] = "hotspot/{$fileName}";

                if ($hasFlash) {
                    $fastApi->executeCommand('/tool/fetch', [
                        'url'      => $fetchUrl,
                        'dst-path' => "flash/hotspot/{$fileName}",
                        'mode'     => 'http',
                    ]);
                }
            } catch (\Throwable $fe) {
                \Illuminate\Support\Facades\Log::warning("Portal file {$fileName} fast-sync error on {$router->name}: " . $fe->getMessage());
            }
        }

        // Sync any extra walled-garden domains
        if (!empty($extraWalledGarden)) {
            try {
                $existingWg = $api->executeCommand('/ip/hotspot/walled-garden/print');
                $existingHosts = [];
                if (is_array($existingWg)) {
                    foreach ($existingWg as $w) {
                        if (!empty($w['dst-host'])) {
                            $existingHosts[] = strtolower($w['dst-host']);
                        }
                    }
                }
                foreach ($extraWalledGarden as $dom) {
                    $cleanDom = trim((string) $dom);
                    if ($cleanDom === '') continue;
                    $pattern = str_contains($cleanDom, '*') ? $cleanDom : "*{$cleanDom}*";
                    if (!in_array(strtolower($pattern), $existingHosts)) {
                        $api->executeCommand('/ip/hotspot/walled-garden/add', [
                            'dst-host' => $pattern,
                            'action'   => 'allow',
                            'comment'  => 'HSmart Payment Gateway',
                        ]);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Clear unauthorized hotspot hosts so phones immediately reload the new Captive Portal design
        try {
            $hosts = $api->executeCommand('/ip/hotspot/host/print');
            if (is_array($hosts)) {
                foreach ($hosts as $h) {
                    if (($h['authorized'] ?? 'false') === 'false') {
                        try {
                            $api->executeCommand('/ip/hotspot/host/remove', [], ['.id' => $h['.id']]);
                        } catch (\Throwable $he) {}
                    }
                }
            }
        } catch (\Throwable $e) {}

        return [
            'success'        => true,
            'files_uploaded' => $uploadedFiles,
        ];
    }
}

