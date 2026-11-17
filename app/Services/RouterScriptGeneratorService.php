<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Crypt;

class RouterScriptGeneratorService
{
    /**
     * Generate idempotent, injection-safe, resilient RouterOS (.rsc) configuration script.
     * Compatible with RouterOS v6 and v7 across all architectures (smips, mipsbe, arm, x86, tile).
     */
    public static function generateScript(Router $router, string $plainToken, string $baseUrl): string
    {
        $safeName = self::escapeString($router->name ?: ($router->router_name ?: 'HSmart-Router'));
        $vpnIp    = $router->vpn_ip ?: '10.10.0.50';

        $wgPrivKey = $router->wg_private_key ?: '';
        try {
            $wgPrivKey = Crypt::decryptString($wgPrivKey);
        } catch (\Throwable $e) {}

        $serverPubKey   = config('services.wireguard.server_public_key', env('WG_SERVER_PUBLIC_KEY', 'DuZQaTGDe95eAhT6+OFgFaI+rsP/n6iaIxjWSvtTRx4='));
        $serverEndpoint = config('services.wireguard.server_endpoint', env('WG_ENDPOINT', '213.199.49.216:51820'));

        $apiUser = self::escapeString($router->api_user ?: ($router->username ?: 'hs_api_' . substr(md5($router->id), 0, 6)));
        
        $apiPass = $router->api_password ?: ($router->password ?: 'HSmartPass!' . rand(1000, 9999));
        try {
            $apiPass = Crypt::decryptString($apiPass);
        } catch (\Throwable $e) {}
        $apiPass = self::escapeString($apiPass);

        [$serverHost, $serverPort] = array_pad(explode(':', $serverEndpoint, 2), 2, 51820);
        $doneUrl  = rtrim($baseUrl, '/') . "/provision/{$plainToken}/done";
        $helloUrl = rtrim($baseUrl, '/') . "/api/router-hello/{$plainToken}";

        $lines = [];
        $lines[] = "# ====================================================================";
        $lines[] = "#  HSMART CLOUD - AUTOMATED ROUTER SETUP SCRIPT";
        $lines[] = "#  Router: {$safeName} | ID: {$router->id}";
        $lines[] = "#  Generated: " . now()->toIso8601String() . " | Idempotent & Fault-Tolerant";
        $lines[] = "# ====================================================================";
        $lines[] = "";

        // 1. Router Identity
        $lines[] = "# --- 1. Router Identity ---";
        $lines[] = ":do { /system identity set name=\"{$safeName}\" } on-error={};";
        $lines[] = "";

        // 2. WireGuard Management Tunnel (v7 only, wrapped safely)
        if (!empty($wgPrivKey)) {
            $lines[] = "# --- 2. WireGuard Management Tunnel ---";
            $lines[] = ":do {";
            $lines[] = "    :if ([:len [/interface wireguard find name=wg-hsmart]] = 0) do={";
            $lines[] = "        /interface wireguard add name=wg-hsmart listen-port=13231 private-key=\"{$wgPrivKey}\" comment=\"HSmart VPN Tunnel\"";
            $lines[] = "    } else={";
            $lines[] = "        /interface wireguard set [find name=wg-hsmart] private-key=\"{$wgPrivKey}\"";
            $lines[] = "    }";
            $lines[] = "} on-error={ :log warning \"HSmart: WireGuard interface not available on this RouterOS build\" };";
            $lines[] = "";

            $lines[] = ":do {";
            $lines[] = "    :if ([:len [/interface find name=wg-hsmart]] > 0) do={";
            $lines[] = "        :if ([:len [/ip address find interface=wg-hsmart]] = 0) do={";
            $lines[] = "            /ip address add address={$vpnIp}/16 interface=wg-hsmart comment=\"HSmart Cloud IP\"";
            $lines[] = "        } else={";
            $lines[] = "            /ip address set [find interface=wg-hsmart] address={$vpnIp}/16";
            $lines[] = "        }";
            $lines[] = "    }";
            $lines[] = "} on-error={};";
            $lines[] = "";

            $lines[] = ":do {";
            $lines[] = "    :if ([:len [/interface find name=wg-hsmart]] > 0) do={";
            $lines[] = "        :if ([:len [/interface wireguard peers find interface=wg-hsmart]] = 0) do={";
            $lines[] = "            /interface wireguard peers add interface=wg-hsmart public-key=\"{$serverPubKey}\" endpoint-address=\"{$serverHost}\" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25s comment=\"HSmart Cloud Gateway\"";
            $lines[] = "        } else={";
            $lines[] = "            /interface wireguard peers set [find interface=wg-hsmart] public-key=\"{$serverPubKey}\" endpoint-address=\"{$serverHost}\" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25s";
            $lines[] = "        }";
            $lines[] = "    }";
            $lines[] = "} on-error={};";
            $lines[] = "";
        }

        // 3. Firewall Security & Hardening (Correct syntax: in-interface, NOT interface)
        $lines[] = "# --- 3. Firewall Rules ---";
        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/interface find name=wg-hsmart]] > 0) do={";
        $lines[] = "        :if ([:len [/ip firewall filter find comment=\"HSmart Inbound Management\"]] = 0) do={";
        $lines[] = "            /ip firewall filter add chain=input in-interface=wg-hsmart action=accept place-before=0 comment=\"HSmart Inbound Management\"";
        $lines[] = "        }";
        $lines[] = "    }";
        $lines[] = "} on-error={};";
        $lines[] = "";

        // 4. Restricted Management API User
        $lines[] = "# --- 4. Restricted Management API User ---";
        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/user group find name=hsmart_api]] = 0) do={";
        $lines[] = "        /user group add name=hsmart_api policy=api,read,write,test,policy,password,ftp,reboot comment=\"HSmart Managed Group\"";
        $lines[] = "    } else={";
        $lines[] = "        /user group set [find name=hsmart_api] policy=api,read,write,test,policy,password,ftp,reboot";
        $lines[] = "    }";
        $lines[] = "} on-error={};";
        $lines[] = "";

        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/user find name=\"{$apiUser}\"]] = 0) do={";
        $lines[] = "        /user add name=\"{$apiUser}\" group=hsmart_api password=\"{$apiPass}\" comment=\"HSmart Automated API\"";
        $lines[] = "    } else={";
        $lines[] = "        /user set [find name=\"{$apiUser}\"] password=\"{$apiPass}\"";
        $lines[] = "    }";
        $lines[] = "} on-error={};";
        $lines[] = "";

        $lines[] = ":do { /ip service enable api } on-error={};";
        $lines[] = ":do { /ip service set api port=8728 } on-error={};";
        $lines[] = "";

        // 5. DNS Resolvers
        $lines[] = "# --- 5. DNS Resolvers ---";
        $lines[] = ":do { /ip dns set allow-remote-requests=yes servers=1.1.1.1,8.8.8.8,1.0.0.1 } on-error={};";
        $lines[] = "";

        // 6. Automated Hotspot, Built-In Wi-Fi Bridge & Captive Portal Provisioning
        $portalBaseUrl = preg_replace('/^https:/i', 'http:', rtrim($baseUrl, '/')) . "/provision/hotspot-portal/{$router->id}";
        $lines[] = "# --- 6. Hotspot Bridge, Built-In Wireless & Captive Portal ---";
        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/interface bridge find name=hs-bridge]] = 0) do={";
        $lines[] = "        /interface bridge add name=hs-bridge comment=\"hsmart-hotspot-bridge\"";
        $lines[] = "    }";
        $lines[] = "    :do { :if ([:len [/interface list find name=LAN]] > 0 && [:len [/interface list member find list=LAN interface=hs-bridge]] = 0) do={ /interface list member add list=LAN interface=hs-bridge } } on-error={};";
        $lines[] = "    :if ([:len [/interface find name=ether2]] > 0) do={";
        $lines[] = "        :if ([:len [/interface bridge port find interface=ether2]] = 0) do={";
        $lines[] = "            /interface bridge port add bridge=hs-bridge interface=ether2";
        $lines[] = "        } else={";
        $lines[] = "            /interface bridge port set [find interface=ether2] bridge=hs-bridge disabled=no";
        $lines[] = "        }";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/interface find name=ether4]] > 0) do={";
        $lines[] = "        :if ([:len [/interface bridge port find interface=ether4]] = 0) do={";
        $lines[] = "            /interface bridge port add bridge=hs-bridge interface=ether4";
        $lines[] = "        } else={";
        $lines[] = "            /interface bridge port set [find interface=ether4] bridge=hs-bridge disabled=no";
        $lines[] = "        }";
        $lines[] = "    }";
        $lines[] = "} on-error={};";
        $lines[] = "";
        // Resolve Tenant Brand SSID for built-in MikroTik Wi-Fi (hAP lite, hAP ac2, hAP ax2, etc.)
        $wifiSsid = "{$safeName} WiFi";
        try {
            $tId = (int) ($router->tenant_id ?: 1);
            $brand = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('isp_portal_settings')) {
                $brand = \Illuminate\Support\Facades\DB::table('isp_portal_settings')->where('tenant_id', $tId)->value('brand_name');
            }
            if (empty($brand)) {
                $brand = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $tId)->value('name');
            }
            if (!empty($brand)) {
                $wifiSsid = self::escapeString(substr(trim($brand), 0, 28));
            }
        } catch (\Throwable $e) {}

        // Auto-enable internal legacy wireless (wlan1 / wlan2 on hAP lite, hAP ac2, hAP ac3)
        foreach (['wlan1' => $wifiSsid, 'wlan2' => substr($wifiSsid, 0, 25) . ' 5G'] as $wIf => $wSsid) {
            $lines[] = ":do {";
            $lines[] = "    :if ([:len [/interface find name={$wIf}]] > 0) do={";
            $lines[] = "        :do { /interface wireless security-profiles set [find name=default] mode=none } on-error={};";
            $lines[] = "        :do { /interface wireless set [find name={$wIf}] mode=ap-bridge ssid=\"{$wSsid}\" security-profile=default default-forwarding=yes disabled=no } on-error={ :do { /interface wireless set [find name={$wIf}] ssid=\"{$wSsid}\" disabled=no } on-error={} };";
            $lines[] = "        :if ([:len [/interface bridge port find interface={$wIf}]] = 0) do={";
            $lines[] = "            /interface bridge port add bridge=hs-bridge interface={$wIf}";
            $lines[] = "        } else={";
            $lines[] = "            /interface bridge port set [find interface={$wIf}] bridge=hs-bridge disabled=no";
            $lines[] = "        }";
            $lines[] = "    }";
            $lines[] = "} on-error={};";
        }
        // Auto-enable RouterOS v7 wifi-qcom/wifiwave2 internal radios (wifi1 / wifi2 on hAP ax2, hAP ax3)
        foreach (['wifi1' => $wifiSsid, 'wifi2' => substr($wifiSsid, 0, 25) . ' 5G'] as $wIf => $wSsid) {
            $lines[] = ":do {";
            $lines[] = "    :if ([:len [/interface find name={$wIf}]] > 0) do={";
            $lines[] = "        :do { /interface wifi set [find name={$wIf}] configuration.mode=ap configuration.ssid=\"{$wSsid}\" security.authentication-types=\"\" disabled=no } on-error={ :do { /interface wifi set [find name={$wIf}] disabled=no } on-error={} };";
            $lines[] = "        :if ([:len [/interface bridge port find interface={$wIf}]] = 0) do={";
            $lines[] = "            /interface bridge port add bridge=hs-bridge interface={$wIf}";
            $lines[] = "        } else={";
            $lines[] = "            /interface bridge port set [find interface={$wIf}] bridge=hs-bridge disabled=no";
            $lines[] = "        }";
            $lines[] = "    }";
            $lines[] = "} on-error={};";
        }
        $lines[] = "";
        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/ip address find comment=\"hsmart-hotspot\"]] = 0) do={";
        $lines[] = "        /ip address add address=10.5.50.1/24 interface=hs-bridge comment=\"hsmart-hotspot\"";
        $lines[] = "    } else={";
        $lines[] = "        /ip address set [find comment=\"hsmart-hotspot\"] address=10.5.50.1/24 interface=hs-bridge disabled=no";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip pool find name=hs-pool-10]] = 0) do={";
        $lines[] = "        /ip pool add name=hs-pool-10 ranges=10.5.50.10-10.5.50.254";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip dhcp-server network find address=\"10.5.50.0/24\"]] = 0) do={";
        $lines[] = "        /ip dhcp-server network add address=10.5.50.0/24 gateway=10.5.50.1 dns-server=10.5.50.1,8.8.8.8 comment=\"hsmart-hotspot\"";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip dhcp-server find name=hs-dhcp-10]] = 0) do={";
        $lines[] = "        /ip dhcp-server add name=hs-dhcp-10 interface=hs-bridge address-pool=hs-pool-10 lease-time=1h disabled=no";
        $lines[] = "    } else={";
        $lines[] = "        /ip dhcp-server set [find name=hs-dhcp-10] interface=hs-bridge address-pool=hs-pool-10 disabled=no";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip firewall nat find comment=\"hsmart-hotspot-masq\"]] = 0) do={";
        $lines[] = "        /ip firewall nat add chain=srcnat action=masquerade src-address=10.5.50.0/24 comment=\"hsmart-hotspot-masq\"";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip hotspot profile find name=hsmart-hsprof]] = 0) do={";
        $lines[] = "        /ip hotspot profile add name=hsmart-hsprof hotspot-address=10.5.50.1 dns-name=hotspot.hsmart.lan html-directory=hotspot login-by=http-pap,http-chap,cookie,mac-cookie";
        $lines[] = "    } else={";
        $lines[] = "        /ip hotspot profile set [find name=hsmart-hsprof] hotspot-address=10.5.50.1 dns-name=hotspot.hsmart.lan html-directory=hotspot login-by=http-pap,http-chap,cookie,mac-cookie";
        $lines[] = "    }";
        $lines[] = "    :if ([:len [/ip hotspot find name=hsmart-hotspot]] = 0) do={";
        $lines[] = "        /ip hotspot add name=hsmart-hotspot interface=hs-bridge profile=hsmart-hsprof address-pool=hs-pool-10 addresses-per-mac=10 disabled=no";
        $lines[] = "    } else={";
        $lines[] = "        /ip hotspot set [find name=hsmart-hotspot] interface=hs-bridge profile=hsmart-hsprof address-pool=hs-pool-10 addresses-per-mac=10 disabled=no";
        $lines[] = "    }";
        $lines[] = "} on-error={};";
        $lines[] = "";
        $lines[] = "# --- Walled Garden & Secondary AP (Tenda/TP-Link) ICMP Bypass ---";
        $lines[] = ":do {";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden find dst-host=\"*.hsmart.co.ke\"]] = 0) do={ /ip hotspot walled-garden add dst-host=\"*.hsmart.co.ke\" action=allow };";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden find dst-host=\"isp.hsmart.co.ke\"]] = 0) do={ /ip hotspot walled-garden add dst-host=\"isp.hsmart.co.ke\" action=allow };";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden find dst-host=\"*.payhero.co.ke\"]] = 0) do={ /ip hotspot walled-garden add dst-host=\"*.payhero.co.ke\" action=allow };";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden find dst-host=\"*.safaricom.co.ke\"]] = 0) do={ /ip hotspot walled-garden add dst-host=\"*.safaricom.co.ke\" action=allow };";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden ip find dst-address=\"{$serverHost}\"]] = 0) do={ /ip hotspot walled-garden ip add dst-address=\"{$serverHost}\" action=accept comment=\"hsmart-cloud-ip\" };";
        $lines[] = "    :if ([:len [/ip hotspot walled-garden ip find comment=\"hsmart-allow-ap-icmp-check\"]] = 0) do={ /ip hotspot walled-garden ip add protocol=icmp action=accept comment=\"hsmart-allow-ap-icmp-check\" };";
        $lines[] = "    :if ([:len [/ip firewall filter find comment=\"hsmart-ap-icmp-allow\"]] = 0) do={ /ip firewall filter add chain=pre-hotspot protocol=icmp action=accept comment=\"hsmart-ap-icmp-allow\" };";
        $lines[] = "} on-error={};";
        $lines[] = "";
        $lines[] = "# --- Download Branded Captive Portal Templates ---";
        foreach (['login.html', 'rlogin.html', 'redirect.html', 'alogin.html', 'flogin.html', 'status.html', 'logout.html', 'error.html'] as $tplFile) {
            $lines[] = ":do { /tool fetch check-certificate=no mode=http url=\"{$portalBaseUrl}/{$tplFile}\" dst-path=\"hotspot/{$tplFile}\" } on-error={};";
            $lines[] = ":do { :if ([:len [/file find name=flash]] > 0) do={ /tool fetch check-certificate=no mode=http url=\"{$portalBaseUrl}/{$tplFile}\" dst-path=\"flash/hotspot/{$tplFile}\" } } on-error={};";
        }
        $lines[] = "";

        // 7. Periodic Heartbeat Telemetry & Activation Callback
        $lines[] = "# --- 6. Telemetry Heartbeat & Activation Callback ---";
        $lines[] = ":do { /system scheduler remove [find name=hsmart_hello] } on-error={};";
        $lines[] = ":do {";
        $lines[] = "    /system scheduler add name=hsmart_hello interval=60s on-event=\":do { /tool fetch check-certificate=no url=\\\"{$helloUrl}\\\" keep-result=no } on-error={}\" comment=\"HSmart Heartbeat\"";
        $lines[] = "} on-error={};";
        $lines[] = "";

        // Instant callbacks
        $lines[] = ":do { /tool fetch check-certificate=no url=\"{$helloUrl}\" keep-result=no } on-error={};";
        $lines[] = ":delay 1s;";
        $lines[] = ":do { /tool fetch check-certificate=no url=\"{$doneUrl}\" dst-path=\"hs_done.txt\" } on-error={ :log warning \"HSmart: Done callback notification skipped\" };";
        $lines[] = ":delay 1s;";
        $lines[] = ":do { /file remove [find name=\"hs_done.txt\"] } on-error={};";
        $lines[] = ":log info \"HSmart: Provisioning completed successfully! Router is now ONLINE.\";";
        $lines[] = ":put \"HSmart Provisioning complete! Router connected to cloud gateway.\";";

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
