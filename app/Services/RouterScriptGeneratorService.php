<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Crypt;

class RouterScriptGeneratorService
{
    /**
     * Generate idempotent, injection-safe RouterOS (.rsc) configuration script.
     */
    public static function generateScript(Router $router, string $plainToken, string $baseUrl): string
    {
        $safeName       = self::escapeString($router->name ?: ($router->router_name ?: 'HSmart-Router'));
        $vpnIp          = $router->vpn_ip ?: '10.10.0.50';

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
        $doneUrl = rtrim($baseUrl, '/') . "/provision/{$plainToken}/done";
        $helloUrl = rtrim($baseUrl, '/') . "/api/router-hello/{$plainToken}";

        $lines = [];
        $lines[] = "# ====================================================================";
        $lines[] = "#  HSMART CLOUD - AUTOMATED ROUTER SETUP SCRIPT";
        $lines[] = "#  Router: {$safeName} | ID: {$router->id}";
        $lines[] = "#  Generated: " . now()->toIso8601String() . " | Idempotent & Safe";
        $lines[] = "# ====================================================================";
        $lines[] = "";

        // 1. Router Identity
        $lines[] = "# --- 1. Router Identity ---";
        $lines[] = "/system identity set name=\"{$safeName}\"";
        $lines[] = "";

        // 2. WireGuard Management Tunnel
        if (!empty($wgPrivKey)) {
            $lines[] = "# --- 2. WireGuard Management Tunnel ---";
            $lines[] = ":if ([:len [/interface wireguard find name=wg-hsmart]] = 0) do={";
            $lines[] = "    /interface wireguard add name=wg-hsmart listen-port=13231 private-key=\"{$wgPrivKey}\" comment=\"HSmart VPN Tunnel\"";
            $lines[] = "} else={";
            $lines[] = "    /interface wireguard set [find name=wg-hsmart] private-key=\"{$wgPrivKey}\"";
            $lines[] = "}";
            $lines[] = ":if ([:len [/ip address find interface=wg-hsmart]] = 0) do={";
            $lines[] = "    /ip address add address={$vpnIp}/16 interface=wg-hsmart comment=\"HSmart Cloud IP\"";
            $lines[] = "} else={";
            $lines[] = "    /ip address set [find interface=wg-hsmart] address={$vpnIp}/16";
            $lines[] = "}";
            $lines[] = ":if ([:len [/interface wireguard peers find interface=wg-hsmart]] = 0) do={";
            $lines[] = "    /interface wireguard peers add interface=wg-hsmart public-key=\"{$serverPubKey}\" endpoint-address=\"{$serverHost}\" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25 comment=\"HSmart Cloud Gateway\"";
            $lines[] = "} else={";
            $lines[] = "    /interface wireguard peers set [find interface=wg-hsmart] public-key=\"{$serverPubKey}\" endpoint-address=\"{$serverHost}\" endpoint-port={$serverPort} allowed-address=10.10.0.0/16 persistent-keepalive=25";
            $lines[] = "}";
            $lines[] = "";
        }

        // 3. Firewall Security & Hardening
        $lines[] = "# --- 3. Firewall Rules ---";
        $lines[] = ":if ([:len [/ip firewall filter find comment=\"HSmart Inbound Management\"]] = 0) do={";
        $lines[] = "    /ip firewall filter add chain=input interface=wg-hsmart action=accept place-before=0 comment=\"HSmart Inbound Management\"";
        $lines[] = "}";
        $lines[] = "";

        // 4. Restricted Management API User
        $lines[] = "# --- 4. Restricted Management API User ---";
        $lines[] = ":if ([:len [/user group find name=hsmart_api]] = 0) do={";
        $lines[] = "    /user group add name=hsmart_api policy=api,read,write,test,policy,password comment=\"HSmart Managed Group\"";
        $lines[] = "}";
        $lines[] = ":if ([:len [/user find name=\"{$apiUser}\"]] = 0) do={";
        $lines[] = "    /user add name=\"{$apiUser}\" group=hsmart_api password=\"{$apiPass}\" comment=\"HSmart Automated API\"";
        $lines[] = "} else={";
        $lines[] = "    /user set [find name=\"{$apiUser}\"] password=\"{$apiPass}\"";
        $lines[] = "}";
        $lines[] = "/ip service enable api";
        $lines[] = "/ip service set api port=8728";
        $lines[] = "";

        // 5. DNS Resolvers
        $lines[] = "# --- 5. DNS Resolvers ---";
        $lines[] = "/ip dns set allow-remote-requests=yes servers=1.1.1.1,8.8.8.8,1.0.0.1";
        $lines[] = "";

        // 6. Periodic Heartbeat Telemetry
        $lines[] = "# --- 6. Telemetry Heartbeat & Activation Callback ---";
        $lines[] = "/system scheduler remove [find name=hsmart_hello]";
        $lines[] = "/system scheduler add name=hsmart_hello interval=15s on-event=\"/tool fetch check-certificate=no url=\\\"{$helloUrl}\\\" keep-result=no\" comment=\"HSmart Heartbeat\"";
        $lines[] = "/tool fetch check-certificate=no url=\"{$helloUrl}\" keep-result=no";
        $lines[] = ":delay 1s;";
        $lines[] = ":do { /tool fetch check-certificate=no url=\"{$doneUrl}\" mode=https dst-path=hs_done.txt } on-error={ :log warning \"HSmart: Done callback notification failed\" }";
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
