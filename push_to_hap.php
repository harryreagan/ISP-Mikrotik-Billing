<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = \App\Models\Router::withoutGlobalScopes()->find(263);
if (!$r) {
    echo "Router 263 not found\n";
    exit(1);
}

echo "Pushing portal files to router {$r->name} (ID {$r->id})...\n";
$res = \App\Services\CaptivePortalTemplates::pushPortalFilesOnly($r);
echo "Portal push result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";

// Also ensure profile login-by is http-pap
$pass = '';
try { $pass = \Illuminate\Support\Facades\Crypt::decryptString($r->api_password ?: $r->password); } catch (\Throwable $e) { $pass = $r->api_password ?: $r->password; }
try {
    $api = new \App\Services\MikrotikApiService('10.10.0.4', 8728, $r->api_user ?: 'admin', $pass, 8);
    $profs = $api->executeCommand('/ip/hotspot/profile/print');
    foreach ($profs as $p) {
        if ($p['name'] === 'hsmart-hsprof' || $p['name'] === 'default') {
            echo "Setting profile {$p['name']} login-by=http-pap\n";
            $api->executeCommand('/ip/hotspot/profile/set', ['login-by' => 'http-pap'], ['.id' => $p['.id']]);
        }
    }
    echo "Profiles updated to http-pap successfully.\n";
} catch (\Throwable $e) {
    echo "Profile update note: " . $e->getMessage() . "\n";
}
