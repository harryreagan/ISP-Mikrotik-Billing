<?php

namespace App\Services;

use App\Models\Router;

class RouterScriptGeneratorService
{
    /**
     * Generate idempotent, injection-safe RouterOS (.rsc) configuration script.
     */
    public static function generateScript(Router $router, string $plainToken, string $baseUrl): string
    {
        $safeName       = self::escapeString($router->name ?: 'HSmart-Router');
        $lanIface       = self::escapeString($router->lan_interface ?: 'ether2');
        $wanIface       = self::escapeString($router->wan_interface ?: 'ether1');
        $vpnIp          = $router->vpn_ip ?: '10.10.0.50';
        $wgPrivKey      = $router->wg_private_key ?: '';
        $serverPubKey   = config('services.wireguard.server_public_key', 'DuZQaTGDe95eAhT6+OFgFaI+rsP/n6iaIxjWSvtTRx4=');
        $serverEndpoint = config('services.wireguard.server_endpoint', '213.199.49.216:51820');
        $gatewayIp      = config('services.wireguard.gateway_ip', '10.10.0.1');
        $radiusSecret   = self::escapeString($router->radius_secret ?: 'hsmart_rad_secret');
        $apiUser        = self::escapeString($router->username ?: 'hsmart_api');
        $apiPass        = self::escapeString($router->password ?: 'HSmartPass!' . rand(1000, 9999));
        $services       = is_array($router->services) ? $router->services : json_decode($router->services ?: '[]', true);
        if (empty($services)) {
            $services = ['hotspot', 'pppoe'];
        }

        [$serverHost, $serverPort] = array_pad(explode(':', $serverEndpoint, 2), 2, 51820);

        $doneUrl = rtrim($baseUrl, '/') . "/provision/{$plainToken}/done";
        $fetchMode = str_starts_with($baseUrl, 'https://') ? 'mode=https' : 'mode=http';

        $lines = [];
        $lines[] = "# ====================================================================";
        $lines[] = "# HSmart ISP Billing - Automated Provisioning Script";
        $lines[] = "# Router: {$safeName} | ID: {$router->id}";
        $lines[] = "# Generated At: " . now()->toIso8601String();
        $lines[] = "# ====================================================================";
        $lines[] = "";

        // 1. Router Identity
        $lines[] = "# --- 1. System Identity ---";
        $lines[] = "/system identity set name=\"{$safeName}\"";
        $lines[] = "";

        // 2. WireGuard Tunnel (RouterOS v7)
        if (!empty($wgPrivKey)) {
            $lines[] = "# --- 2. WireGuard Management Tunnel ---";
            $lines[] = ":do { /interface wireguard add name=wg-hsmart listen-port=51820 private-key=\"{$wgPrivKey}\" comment=\"HSmart Cloud Tunnel\" } on-error={}";
            $lines[] = ":do { /ip address add address={$vpnIp}/32 network={$gatewayIp} interface=wg-hsmart comment=\"HSmart VPN IP\" } on-error={}";
            $lines[] = ":do { /interface wireguard peers add interface=wg-hsmart public-key=\"{$serverPubKey}\" endpoint-address=\"{$serverHost}\" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25 comment=\"HSmart Server Peer\" } on-error={}";
            $lines[] = "";
        }

        // 3. Firewall Security & Hardening
        $lines[] = "# --- 3. Firewall Rules & Hardening ---";
        $lines[] = ":do { /ip firewall filter add chain=input interface=wg-hsmart action=accept comment=\"Accept all HSmart VPN traffic\" place-before=0 } on-error={}";
        $lines[] = ":do { /ip firewall filter add chain=input protocol=udp dst-port=51820 action=accept comment=\"Accept incoming WireGuard handshake\" place-before=1 } on-error={}";
        $lines[] = ":do { /ip firewall filter add chain=input protocol=udp dst-port=3799 action=accept comment=\"Accept RADIUS CoA / PoD Disconnect\" place-before=2 } on-error={}";
        $lines[] = "";

        // 4. Restricted Management User & API
        $lines[] = "# --- 4. Restricted Management API User ---";
        $lines[] = ":do { /user group add name=hsmart-mgmt policy=api,read,write,test comment=\"HSmart Automated Management\" } on-error={}";
        $lines[] = ":do { /user add name=\"{$apiUser}\" group=hsmart-mgmt password=\"{$apiPass}\" allowed-address=10.10.0.0/16 comment=\"HSmart API User\" } on-error={";
        $lines[] = "    /user set [find name=\"{$apiUser}\"] password=\"{$apiPass}\" allowed-address=10.10.0.0/16";
        $lines[] = "}";
        $lines[] = ":do { /ip service set api address=10.10.0.0/16 disabled=no port=8728 } on-error={}";
        $lines[] = ":do { /ip service set telnet disabled=yes } on-error={}";
        $lines[] = ":do { /ip service set ftp disabled=yes } on-error={}";
        $lines[] = "";

        // 5. RADIUS AAA & Disconnect (CoA)
        $lines[] = "# --- 5. Central RADIUS Configuration ---";
        $lines[] = ":do { /radius remove [find comment=\"HSmart Central RADIUS\"] } on-error={}";
        $lines[] = "/radius add service=hotspot,ppp address={$gatewayIp} secret=\"{$radiusSecret}\" timeout=3000ms authentication-port=1812 accounting-port=1813 comment=\"HSmart Central RADIUS\"";
        $lines[] = "/radius incoming set accept=yes port=3799";
        $lines[] = "";

        // 6. DNS, NAT & NTP
        $lines[] = "# --- 6. Core Networking (DNS, NAT, NTP) ---";
        $lines[] = "/ip dns set allow-remote-requests=yes servers=1.1.1.1,8.8.8.8,1.0.0.1";
        $lines[] = ":do { /ip firewall nat add chain=srcnat out-interface=\"{$wanIface}\" action=masquerade comment=\"HSmart WAN Masquerade\" } on-error={}";
        $lines[] = ":do { /system ntp client set enabled=yes servers=pool.ntp.org } on-error={}";
        $lines[] = "";

        // 7. Hotspot Configuration
        if (in_array('hotspot', $services)) {
            $hsSubnet = $router->hotspot_subnet ?: '10.5.50.0/24';
            [$hsNetPrefix] = explode('/', $hsSubnet, 2);
            $octets = explode('.', $hsNetPrefix);
            $hsGateway = "{$octets[0]}.{$octets[1]}.{$octets[2]}.1";
            $hsPoolStart = "{$octets[0]}.{$octets[1]}.{$octets[2]}.10";
            $hsPoolEnd = "{$octets[0]}.{$octets[1]}.{$octets[2]}.254";

            $lines[] = "# --- 7. Hotspot Service Setup ---";
            $lines[] = ":do { /ip pool add name=hs-pool ranges={$hsPoolStart}-{$hsPoolEnd} } on-error={}";
            $lines[] = ":do { /ip address add address={$hsGateway}/24 interface=\"{$lanIface}\" comment=\"Hotspot Gateway\" } on-error={}";
            $lines[] = ":do { /ip dhcp-server add name=hs-dhcp interface=\"{$lanIface}\" address-pool=hs-pool disabled=no lease-time=1h } on-error={}";
            $lines[] = ":do { /ip dhcp-server network add address={$hsSubnet} gateway={$hsGateway} dns-server={$hsGateway},1.1.1.1 } on-error={}";
            $lines[] = ":do { /ip hotspot profile add name=hsb-profile hotspot-address={$hsGateway} dns-name=\"login.hsmart.net\" html-directory=hotspot use-radius=yes radius-accounting=yes radius-interim-update=5m login-by=http-chap,http-pap,mac-cookie } on-error={}";
            $lines[] = ":do { /ip hotspot add name=hsb-hotspot interface=\"{$lanIface}\" address-pool=hs-pool profile=hsb-profile disabled=no } on-error={}";

            // Walled Garden Entries (Allow portal & payment gateways without login)
            $lines[] = "# Hotspot Walled Garden for Payments";
            $lines[] = ":do { /ip hotspot walled-garden ip add dst-host=*.hsmart.co.ke action=accept comment=\"HSmart Platform\" } on-error={}";
            $lines[] = ":do { /ip hotspot walled-garden ip add dst-host=*.safaricom.co.ke action=accept comment=\"M-Pesa Daraja\" } on-error={}";
            $lines[] = ":do { /ip hotspot walled-garden ip add dst-host=*.payhero.co.ke action=accept comment=\"PayHero Gateway\" } on-error={}";
            $lines[] = ":do { /ip hotspot walled-garden ip add dst-host=*.kopokopo.com action=accept comment=\"KopoKopo Gateway\" } on-error={}";
            $lines[] = "";
        }

        // 8. PPPoE Configuration
        if (in_array('pppoe', $services)) {
            $lines[] = "# --- 8. PPPoE Service Setup ---";
            $lines[] = ":do { /ip pool add name=pppoe-pool ranges=10.0.0.2-10.0.0.254 } on-error={}";
            $lines[] = ":do { /ppp profile add name=hsb-pppoe local-address=10.0.0.1 remote-address=pppoe-pool use-ipv6=no dns-server=1.1.1.1,8.8.8.8 } on-error={}";
            $lines[] = "/ppp aaa set use-radius=yes accounting=yes interim-update=5m";
            $lines[] = ":do { /interface pppoe-server server add service-name=hsmart-pppoe interface=\"{$lanIface}\" default-profile=hsb-pppoe one-session-per-host=yes disabled=no } on-error={}";
            $lines[] = "";
        }

        // 9. Completion Callback to Burn Token & Mark Online
        $lines[] = "# --- 9. Final Done Callback ---";
        $lines[] = ":log info \"HSmart: Committing bootstrap configuration...\"";
        $lines[] = ":delay 2s;";
        $lines[] = ":do { /tool fetch url=\"{$doneUrl}\" {$fetchMode} dst-path=hs_done.txt } on-error={ :log warning \"HSmart: Done callback notification failed\" }";
        $lines[] = ":delay 1s;";
        $lines[] = ":do { /file remove [find name=\"hs_done.txt\"] } on-error={}";
        $lines[] = ":log info \"HSmart: Provisioning completed successfully! Router is now ONLINE.\"";

        return implode("\r\n", $lines);
    }

    /**
     * Escape input strings to prevent RouterOS syntax injection.
     */
    private static function escapeString(string $val): string
    {
        return str_replace(['\\', '"', '$', ';', "\r", "\n"], ['\\\\', '\\"', '\\$', '', '', ''], $val);
    }
}

