<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HSmart ISP – Automated MikroTik & M-Pesa WiFi Billing for Kenya</title>
<meta name="description" content="Bill WiFi customers, sell hotspot and PPPoE plans and get paid on M-Pesa. Built for Kenyan ISPs.">

<!-- Jost Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  --font-sans: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --bg-page: #FAFAF8;
  --card-bg: #FFFFFF;
  --card-border: #E6E3F5;
  --card-radius: 18px;
  --ink: #111111;
  --text-secondary: #666666;
  --primary-orange: #F47B06;
  --primary-orange-hover: #E86F00;
  --accent-purple: #6D3FE0;
  --dark-navy: #161B2E;
}

body {
  margin: 0;
  background: var(--bg-page);
  color: var(--ink);
  font-family: var(--font-sans);
  -webkit-font-smoothing: antialiased;
}

a {
  text-decoration: none;
  color: inherit;
  transition: color .2s;
}

.pg {
  background: var(--bg-page);
  color: var(--ink);
  overflow-x: hidden;
}

.w {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 32px;
  box-sizing: border-box;
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  letter-spacing: -.01em;
  padding: 16px 28px;
  border-radius: 12px;
  font-weight: 800;
  font-family: var(--font-sans);
  min-height: 44px;
  box-sizing: border-box;
  transition: transform .2s, box-shadow .2s, background-color .2s;
  cursor: pointer;
}

.btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px rgba(0,0,0,.15);
}

.btn:focus-visible {
  outline: 3px solid var(--accent-purple);
  outline-offset: 2px;
}

.y {
  background: var(--primary-orange);
  color: #111;
}

.y:hover {
  background: var(--primary-orange-hover);
}

.k {
  background: #111;
  color: #fff;
}

.o {
  border: 2px solid #111;
  padding: 14px 26px;
  background: transparent;
  color: #111;
}

.o:hover {
  background: #111;
  color: #fff;
}

.nav {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  padding-top: 20px;
  padding-bottom: 20px;
}

.nl {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
  align-items: center;
  font-weight: 600;
  font-size: 15px;
}

.nl a:not(.btn):hover {
  color: var(--accent-purple);
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 800;
  font-size: 20px;
  color: var(--ink);
}

.lm {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: var(--primary-orange);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #111;
  font-weight: 800;
  font-size: 18px;
}

.hero {
  padding-top: 48px;
  padding-bottom: 72px;
  align-items: center;
  gap: 56px;
}

.mono {
  font-family: var(--font-sans);
  letter-spacing: .08em;
  text-transform: uppercase;
}

.pill {
  font-family: var(--font-sans);
  text-transform: uppercase;
  letter-spacing: .06em;
  align-self: flex-start;
  background: #EFEAFE;
  color: #4C2BD6;
  padding: 8px 16px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 14px;
}

h1 {
  margin: 0;
  font-size: clamp(38px, 5vw, 62px);
  line-height: 1.04;
  font-weight: 800;
}

h2 {
  margin: 0 0 12px;
  font-size: clamp(30px, 3.6vw, 44px);
  line-height: 1.1;
  font-weight: 800;
}

.lead {
  margin: 0;
  font-size: 19px;
  line-height: 1.6;
  color: var(--text-secondary);
}

.ck {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 22px;
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.dash {
  position: relative;
  border: 1px solid var(--card-border);
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 30px 70px rgba(0,0,0,.08);
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.dots {
  display: flex;
  gap: 6px;
}

.dots i {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e5e5e5;
  display: block;
}

.st {
  flex: 1 1 90px;
  background: #fff;
  border: 1px solid var(--card-border);
  border-radius: 12px;
  padding: 12px;
}

.st b {
  display: block;
  font-size: 22px;
}

.st span {
  font-size: 12px;
  color: var(--text-secondary);
}

.bars {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  height: 110px;
  padding: 8px 4px;
  border-bottom: 1px solid #eee;
}

.bars i {
  flex: 1;
  background: var(--primary-orange);
  border-radius: 6px 6px 0 0;
  transform-origin: bottom;
  animation: grow 1.2s ease-out both;
  display: block;
}

.li {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  padding: 10px 12px;
  border: 1px solid #f0f0f0;
  border-radius: 10px;
}

.live {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--primary-orange);
  display: inline-block;
  animation: pulse 1.6s infinite;
}

.fl {
  position: absolute;
  left: -28px;
  bottom: -26px;
  background: var(--dark-navy);
  color: #fff;
  border-radius: 16px;
  padding: 14px 18px;
  display: flex;
  gap: 12px;
  align-items: center;
  box-shadow: 0 18px 40px rgba(0,0,0,.25);
  animation: float 4s ease-in-out infinite;
}

.fl b {
  display: block;
  font-size: 14px;
}

.fl span {
  font-size: 12px;
  color: #ccc;
}

.ok {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary-orange);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #111;
}

.mq {
  border-top: 1px solid #f0f0f0;
  border-bottom: 1px solid #f0f0f0;
  background: #fafafa;
  padding: 22px 0;
  overflow: hidden;
}

.mt {
  display: flex;
  gap: 16px;
  width: max-content;
  animation: scroll 28s linear infinite;
}

.mt span {
  padding: 10px 22px;
  border: 1px solid #e5e5e5;
  border-radius: 999px;
  font-weight: 600;
  background: #fff;
  white-space: nowrap;
}

.sec {
  padding-top: 88px;
  padding-bottom: 88px;
}

.ctr {
  text-align: center;
  max-width: 680px;
  margin: 0 auto 48px;
}

.ctr p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 18px;
  line-height: 1.6;
}

.card {
  flex: 1 1 300px;
  background: #FFF6EC;
  border: 1px solid #FFD8B0;
  border-radius: 20px;
  padding: 28px;
  transition: transform .25s, box-shadow .25s;
}

.card:hover {
  transform: translateY(-6px);
  box-shadow: 0 18px 40px rgba(109,63,224,.15);
}

.card h3 {
  margin: 16px 0 8px;
  font-size: 20px;
}

.card p {
  margin: 0;
  color: #444;
  line-height: 1.6;
}

.ib {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: var(--primary-orange);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ic {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: #111;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.dk {
  background: var(--dark-navy);
  color: #fff;
}

.stp {
  flex: 1 1 260px;
  border-top: 3px solid var(--primary-orange);
  padding-top: 20px;
}

.stp b {
  font-size: 44px;
  color: var(--primary-orange);
}

.stp h3 {
  margin: 6px 0 8px;
  font-size: 21px;
}

.stp p {
  margin: 0;
  color: #ccc;
  line-height: 1.6;
}

.pc {
  flex: 1 1 280px;
  border: 1px solid var(--card-border);
  border-radius: 22px;
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: #fff;
  transition: transform .25s;
}

.pc.hl {
  border: 0;
  background: linear-gradient(135deg, #7C3AED, #5B34E6);
  color: #fff;
  transform: translateY(-8px);
}

.pc.hl ul {
  color: #eee;
}

.pc.hl small {
  color: #ddd !important;
}

.pc.hl .btn.y {
  background: var(--primary-orange);
  color: #111;
}

.pc.hl .btn.y:hover {
  background: var(--primary-orange-hover);
}

.pc h3 {
  margin: 0;
  font-size: 22px;
}

.pc .pr {
  font-size: 34px;
  font-weight: 800;
}

.pc ul {
  margin: 0;
  padding-left: 20px;
  color: #444;
  line-height: 1.9;
}

.fq {
  border-bottom: 1px solid #e5e5e5;
  padding: 20px 0;
}

.fq summary {
  font-weight: 800;
  font-size: 18px;
  cursor: pointer;
  list-style: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.fq summary::-webkit-details-marker {
  display: none;
}

.fq summary::after {
  content: "+";
  font-size: 22px;
  color: var(--accent-purple);
  font-weight: 700;
}

.fq[open] summary::after {
  content: "−";
}

.fq p {
  margin: 12px 0 0;
  color: var(--text-secondary);
  line-height: 1.6;
}

.cta {
  background: linear-gradient(120deg, #6D3FE0, #1E88E5);
  color: #fff;
  padding: 64px 0;
}

.cta .o {
  border-color: #fff;
  color: #fff;
}

.cta .o:hover {
  background: #fff;
  color: #111;
}

.ft {
  background: var(--dark-navy);
  color: #bbb;
  padding: 64px 0 28px;
}

.ft h4 {
  margin: 0 0 14px;
  color: #fff;
  font-size: 16px;
}

.ft a {
  display: block;
  margin-bottom: 10px;
  font-size: 14px;
}

.ft a:hover {
  color: var(--primary-orange);
}

.rv {
  animation: up .8s both;
}

.d1 { animation-delay: .1s; }
.d2 { animation-delay: .25s; }
.d3 { animation-delay: .4s; }

@supports (animation-timeline:view()){
  .sc {
    animation: up .8s both;
    animation-timeline: view();
    animation-range: entry 0% cover 28%;
  }
}

@keyframes up {
  from { opacity: 0; transform: translateY(28px); }
  to { opacity: 1; transform: none; }
}

@keyframes grow {
  from { transform: scaleY(0); }
  to { transform: scaleY(1); }
}

@keyframes float {
  50% { transform: translateY(-12px); }
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(244,123,6,.7); }
  100% { box-shadow: 0 0 0 10px rgba(244,123,6,0); }
}

@keyframes scroll {
  to { transform: translateX(-50%); }
}

.g1 { background: linear-gradient(135deg, #7C3AED, #5B34E6); }
.g2 { background: linear-gradient(135deg, #0EA5E9, #1E88E5); }
.g3 { background: linear-gradient(135deg, #F59E0B, #E8790B); }
.g4 { background: linear-gradient(135deg, #10B981, #059669); }
.g5 { background: #1B2238; }
.g6 { background: linear-gradient(135deg, #EC4899, #DB2777); }

.ib .ic {
  stroke: #fff;
}

.tile {
  flex: 1 1 150px;
  border-radius: 14px;
  padding: 14px;
  color: #fff;
  display: flex;
  flex-direction: column;
  gap: 6px;
  animation: up .8s both;
}

.tile span {
  font-size: 11px;
  letter-spacing: .08em;
  opacity: .85;
  font-family: var(--font-sans);
}

.tile b {
  font-size: 24px;
}

.tile small {
  font-size: 13px;
  opacity: .7;
  font-weight: 500;
}

.tile em {
  font-style: normal;
  font-size: 12px;
  opacity: .85;
}

.rt {
  background: var(--dark-navy);
  color: #fff;
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.rt .h {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 700;
  font-size: 14px;
}

.rt .bd {
  font-size: 11px;
  background: #10B981;
  padding: 3px 10px;
  border-radius: 999px;
}

.pb {
  height: 6px;
  border-radius: 3px;
  background: #2B3350;
  overflow: hidden;
  margin-top: 6px;
}

.pb i {
  display: block;
  height: 100%;
  background: #10B981;
  transform-origin: left;
  animation: gx 1.4s ease-out both;
}

.rt .lb {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #aab;
}

@keyframes gx {
  from { transform: scaleX(0); }
}

/* Responsive Breakpoints (390px, 768px, 1280px) */
@media (max-width: 768px) {
  .w { padding: 0 20px; }
  .hero { padding-top: 32px; padding-bottom: 48px; gap: 40px; }
  .nl { gap: 16px; font-size: 14px; }
  .sec { padding-top: 60px; padding-bottom: 60px; }
}

@media (max-width: 480px) {
  .w { padding: 0 16px; }
  .nav { flex-direction: column; align-items: flex-start; gap: 14px; }
  .nl { width: 100%; justify-content: flex-start; gap: 12px; }
  .hero { padding-top: 24px; padding-bottom: 36px; gap: 28px; }
  .fl { position: static; margin-top: 14px; }
  .pc.hl { transform: none; }
  .btn { width: 100%; }
  .row { gap: 16px; }
}

@media (prefers-reduced-motion:reduce) {
  * { animation: none !important; }
}

html {
  scroll-behavior: smooth;
}
</style>
</head>
<body>
@php
    $loginUrl = Route::has('login') ? route('login') : (Route::has('filament.portal.auth.login') ? route('filament.portal.auth.login') : url('/login'));
    $registerUrl = Route::has('register') ? route('register') : (Route::has('filament.portal.auth.register') ? route('filament.portal.auth.register') : '#demo');
    $whatsAppUrl = 'https://wa.me/254743606108?text=' . urlencode("Hello HSmart ISP, I'd like to book a demo.");
    $phone = '+254 743 606 108';
    $email = 'support@hsmart.co.ke';
@endphp

<div class="pg">
<header class="w nav">
    <a href="#top" class="logo">
        <span class="lm">H</span>HSmart ISP
    </a>
    <nav class="nl">
        <a href="#features">Features</a>
        <a href="#how">How it works</a>
        <a href="#pricing">Pricing</a>
        <a href="#faq">FAQ</a>
        <a href="{{ $loginUrl }}">Login</a>
        <a href="{{ $registerUrl }}" class="btn y" style="padding:12px 22px">Start free trial</a>
    </nav>
</header>

<section class="w row hero" id="top">
    <div class="rv" style="flex:1 1 440px;display:flex;flex-direction:column;gap:22px">
        <span class="pill">Built for Kenyan ISPs and hotspot owners</span>
        <h1>Bill your WiFi customers. <span style="color:var(--accent-purple)">Get paid on M-Pesa.</span></h1>
        <p class="lead" style="max-width:540px">Connect your MikroTik routers, sell hotspot packages and PPPoE plans, and activate customers automatically the moment they pay.</p>
        <div class="row" style="gap:14px">
            <a href="{{ $registerUrl }}" class="btn y">Start free trial</a>
            <a href="#how" class="btn o">See how it works</a>
        </div>
        <div class="ck">
            <span>✓ No manual activation</span>
            <span>✓ Works with Paybill and Till</span>
            <span>✓ SMS reminders</span>
        </div>
    </div>
    <div class="rv d2" style="flex:1 1 440px;position:relative;padding-bottom:20px">
        <div class="dash">
            <div class="dots"><i></i><i></i><i></i></div>
            <div class="row" style="gap:12px">
                <div class="tile g1">
                    <span>ACTIVE PPPOE USERS</span>
                    <b>482<small> / 560</small></b>
                    <em>24 new this month</em>
                </div>
                <div class="tile g2">
                    <span>TODAY’S PPPOE SALES</span>
                    <b>KES 12,400</b>
                    <em>PPPoE only</em>
                </div>
            </div>
            <div class="row" style="gap:12px">
                <div class="tile g3" style="animation-delay:.15s">
                    <span>HOTSPOT SALES</span>
                    <b>KES 6,850</b>
                    <em>Hotspot only</em>
                </div>
                <div class="tile g4" style="animation-delay:.3s">
                    <span>TOTAL REVENUE</span>
                    <b>KES 398,835</b>
                    <em>Pending due: KES 34,568</em>
                </div>
            </div>
            <div class="rt">
                <div class="h">
                    <span>MikroTik CCR · Core router</span>
                    <span class="bd">Online</span>
                </div>
                <div>
                    <div class="lb"><span>CPU</span><span>2%</span></div>
                    <div class="pb"><i style="width:8%"></i></div>
                </div>
                <div>
                    <div class="lb"><span>Memory</span><span>6%</span></div>
                    <div class="pb"><i style="width:22%;animation-delay:.2s"></i></div>
                </div>
            </div>
            <div class="fl">
                <span class="ok">
                    <svg class="ic" viewBox="0 0 24 24"><path d="M5 12l5 5 9-10"></path></svg>
                </span>
                <div>
                    <b>M-Pesa payment received</b>
                    <span>KES 50 · customer connected</span>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="mq">
    <div class="mt">
        <span>M-Pesa</span>
        <span>MikroTik</span>
        <span>Daraja API</span>
        <span>PayHero</span>
        <span>KopoKopo</span>
        <span>Africa’s Talking</span>
        <span>WireGuard VPN</span>
        <span>M-Pesa</span>
        <span>MikroTik</span>
        <span>Daraja API</span>
        <span>PayHero</span>
        <span>KopoKopo</span>
        <span>Africa’s Talking</span>
        <span>WireGuard VPN</span>
    </div>
</div>

<section class="w sec" id="features">
    <div class="ctr sc">
        <h2>Everything you need to run a WiFi business</h2>
        <p>From the first payment to the last router sync, it is handled for you.</p>
    </div>
    <div class="row">
        <div class="card sc">
            <div class="ib g1">
                <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"></rect><path d="M16 12.5h3"></path></svg>
            </div>
            <h3>M-Pesa auto-activation</h3>
            <p>STK Push, Paybill, Till and SMS matching turn payments into active customers in seconds.</p>
        </div>
        <div class="card sc">
            <div class="ib g2">
                <svg class="ic" viewBox="0 0 24 24"><path d="M2 9a15 15 0 0 1 20 0M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0"></path><circle cx="12" cy="20" r="1"></circle></svg>
            </div>
            <h3>Hotspot and PPPoE</h3>
            <p>Time, data and speed plans, vouchers, monthly billing, expiry and reconnect on your MikroTik.</p>
        </div>
        <div class="card sc">
            <div class="ib g3">
                <svg class="ic" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20a6 6 0 0 1 12 0M17 11a3 3 0 0 0 0-6M21 20a5 5 0 0 0-4-5"></path></svg>
            </div>
            <h3>Resellers and agents</h3>
            <p>Agent wallets, commissions and printable voucher sheets to grow beyond your own area.</p>
        </div>
        <div class="card sc">
            <div class="ib g4">
                <svg class="ic" viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4zM10 21h4"></path></svg>
            </div>
            <h3>SMS reminders</h3>
            <p>Bill reminders, expiry alerts and payment receipts sent automatically to your customers.</p>
        </div>
        <div class="card sc">
            <div class="ib g5">
                <svg class="ic" viewBox="0 0 24 24"><path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"></path></svg>
            </div>
            <h3>Reports and live monitoring</h3>
            <p>See revenue, who is online and router health from one clean dashboard.</p>
        </div>
        <div class="card sc">
            <div class="ib g6">
                <svg class="ic" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"></path></svg>
            </div>
            <h3>Secure by design</h3>
            <p>Duplicate payment protection, audit trail, admin roles and routers connected over VPN.</p>
        </div>
    </div>
</section>

<section class="dk sec" id="how">
    <div class="w">
        <div class="ctr sc">
            <h2>Live in three steps</h2>
            <p style="color:#bbb">No complicated setup. Start selling the same day.</p>
        </div>
        <div class="row" style="gap:40px">
            <div class="stp sc">
                <b>1</b>
                <h3>Connect your router</h3>
                <p>Add your MikroTik securely and test the connection in minutes.</p>
            </div>
            <div class="stp sc">
                <b>2</b>
                <h3>Create your packages</h3>
                <p>Set price, time, data and speed for hotspot and PPPoE plans.</p>
            </div>
            <div class="stp sc">
                <b>3</b>
                <h3>Customers pay, you earn</h3>
                <p>M-Pesa payment activates them instantly. No manual work, no missed income.</p>
            </div>
        </div>
    </div>
</section>

<section class="w sec" id="pricing">
    <div class="ctr sc">
        <h2>Simple pricing</h2>
        <p>Pick a plan that fits your network. Change any time.</p>
    </div>
    @php
        $dynamicPlans = null;
        try {
            $rawPlans = \Illuminate\Support\Facades\DB::table('main_site_data')->where('type', 'pricing_plans')->value('value');
            if ($rawPlans) {
                $decoded = json_decode($rawPlans, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $dynamicPlans = $decoded;
                }
            }
        } catch (\Throwable $e) {}

        if (!$dynamicPlans) {
            $dynamicPlans = [
                [
                    'name' => 'Starter',
                    'price' => 'KES 350',
                    'period' => '/month',
                    'features' => ['2 routers', '150 customers', 'M-Pesa payments', 'SMS reminders', 'Unlimited Users'],
                    'button_text' => 'Choose Starter',
                    'button_url' => $registerUrl,
                    'highlighted' => false,
                    'badge' => '',
                ],
                [
                    'name' => 'Business',
                    'price' => 'KES 1000',
                    'period' => '/month',
                    'features' => ['5 routers', '500 customers', 'Resellers and vouchers', 'SMS reminders', 'Unlimited Users'],
                    'button_text' => 'Choose Business',
                    'button_url' => $registerUrl,
                    'highlighted' => true,
                    'badge' => 'Popular',
                ],
                [
                    'name' => 'Enterprise',
                    'price' => 'Custom',
                    'period' => '',
                    'features' => ['Unlimited routers', 'Custom branding', 'Priority support', 'Onboarding help'],
                    'button_text' => 'Talk to us',
                    'button_url' => $whatsAppUrl,
                    'highlighted' => false,
                    'badge' => '',
                ],
            ];
        }
    @endphp
    <div class="row" style="align-items:stretch">
        @foreach($dynamicPlans as $p)
        <div class="pc {{ !empty($p['highlighted']) ? 'hl' : '' }} sc" style="position:relative">
            @if(!empty($p['badge']))
            <span style="position:absolute;top:18px;right:18px;background:linear-gradient(135deg, #F47B06, #FF9933);color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.06em;box-shadow:0 2px 8px rgba(244,123,6,0.35)">{{ $p['badge'] }}</span>
            @endif
            <h3>{{ $p['name'] }}</h3>
            <div class="pr">{{ $p['price'] }}@if(!empty($p['period']))<small style="font-size:15px;font-weight:600;color:{{ !empty($p['highlighted']) ? '#eee' : 'var(--text-secondary)' }}">{{ $p['period'] }}</small>@endif</div>
            <ul>
                @foreach($p['features'] ?? [] as $feat)
                <li>{{ $feat }}</li>
                @endforeach
            </ul>
            <a href="{{ !empty($p['button_url']) ? $p['button_url'] : $registerUrl }}" class="btn {{ !empty($p['highlighted']) ? 'y' : 'o' }}" style="text-align:center" {!! str_contains($p['button_url'] ?? '', 'wa.me') ? 'target="_blank" rel="noopener noreferrer"' : '' !!}>{{ $p['button_text'] ?? 'Choose Plan' }}</a>
        </div>
        @endforeach
    </div>
</section>

<section class="w" id="faq" style="padding-bottom:88px;max-width:860px">
    <div class="ctr sc">
        <h2>Frequently Asked Questions</h2>
    </div>
    @php
        $dynamicFaqs = null;
        try {
            $rawFaqs = \Illuminate\Support\Facades\DB::table('main_site_data')->where('type', 'landing_faqs')->value('value');
            if ($rawFaqs) {
                $decodedFaqs = json_decode($rawFaqs, true);
                if (is_array($decodedFaqs) && !empty($decodedFaqs)) {
                    $dynamicFaqs = $decodedFaqs;
                }
            }
        } catch (\Throwable $e) {}

        if (!$dynamicFaqs) {
            $dynamicFaqs = [
                ['q' => 'Do I need a Daraja or Paybill account?', 'a' => 'No. You can also receive payments on a Till or personal number using automated M-Pesa SMS matching.'],
                ['q' => 'Which routers are supported?', 'a' => 'All MikroTik RouterOS devices (RouterOS v6 & v7) with the API or API-SSL service enabled, connected over public IP or WireGuard VPN.'],
                ['q' => 'Can I sell vouchers through agents?', 'a' => 'Yes. Resellers get a dedicated portal, agent wallet, live commissions, and printable voucher sheets with QR codes.'],
                ['q' => 'What happens if a customer pays twice?', 'a' => 'Each M-Pesa transaction code can only be used once. Our duplicate payment protection ensures transactions are never credited twice.'],
            ];
        }
    @endphp
    @foreach($dynamicFaqs as $faq)
    <details class="fq">
        <summary>{{ $faq['q'] }}</summary>
        <p>{{ $faq['a'] }}</p>
    </details>
    @endforeach
</section>

<section class="cta" id="demo">
    <div class="w row" style="align-items:center;justify-content:space-between">
        <div class="sc" style="font-size:clamp(28px,3.4vw,40px);font-weight:800;max-width:660px;line-height:1.15">
            Ready to automate your WiFi business?
        </div>
        <div class="row" style="gap:14px">
            <a href="{{ $registerUrl }}" class="btn y">Start free trial</a>
            <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer" class="btn o">Book a demo</a>
        </div>
    </div>
</section>

<footer class="ft">
    <div class="w">
        <div class="row" style="gap:40px;justify-content:space-between">
            <div style="flex:1 1 280px;max-width:340px">
                <div class="logo" style="color:#fff">
                    <span class="lm">H</span>HSmart ISP
                </div>
                <p style="line-height:1.7;font-size:14px;color:#aab">
                    Automated MikroTik ISP and hotspot billing, built in Kenya for instant local M-Pesa payments.
                </p>
            </div>
            <div>
                <h4>Product</h4>
                <a href="#features">Features</a>
                <a href="#how">How it works</a>
                <a href="#pricing">Pricing</a>
            </div>
            <div>
                <h4>Company</h4>
                <a href="#top">About</a>
                <a href="#faq">FAQ</a>
                <a href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">Contact</a>
            </div>
            <div>
                <h4>Contact</h4>
                <a href="tel:{{ str_replace(' ', '', $phone) }}">{{ $phone }}</a>
                <a href="mailto:{{ $email }}">{{ $email }}</a>
                <a href="#top">Nairobi, Kenya</a>
            </div>
        </div>
        <div style="border-top:1px solid #333;margin-top:40px;padding-top:20px;font-size:13px;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between">
            <span class="mono">© {{ date('Y') }} HSmart Technologies Ltd. All rights reserved.</span>
            <span><a href="{{ route('policy.show') }}" style="color:#aab;text-decoration:underline;">Privacy Policy</a> · <a href="{{ route('terms.show') }}" style="color:#aab;text-decoration:underline;">Terms of Service</a></span>
        </div>
    </div>
</footer>
</div>
</body>
</html>

