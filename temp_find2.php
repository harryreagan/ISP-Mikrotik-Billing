<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$all = App\Models\Router::all();
foreach ($all as $r) {
    if (str_contains($r->setup_token, 'YhH') || str_contains($r->name, 'ILVY')) {
        echo "ID {$r->id}: name={$r->name}, setup={$r->setup_token}, prov={$r->provision_token}
";
    }
}
