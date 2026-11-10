<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = 'nbp_YhHj7pQJH0IFsCXPYNMCyREqvvd7mS3UK4IM';
$r = App\Models\Router::where('setup_token', $token)->orWhere('provision_token', $token)->first();
if ($r) {
    echo "Found router #{$r->id}: {$r->name}, status={$r->status}
";
} else {
    echo "NOT FOUND for {$token}
";
    $latest = App\Models\Router::latest('id')->take(3)->get();
    foreach ($latest as $l) {
        echo "ID {$l->id}: name={$l->name}, setup_token={$l->setup_token}
";
    }
}
