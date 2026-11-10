<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RouteSmokeTest extends Command
{
    protected $signature = 'test:smoke-routes {--role=all : Role to test (super-admin, isp-admin, reseller, guest, all)}';
    protected $description = 'Automated route smoke test: crawls GET routes for roles and fails on any 5xx error';

    public function handle(): int
    {
        $this->info("Starting automated route smoke test...");
        
        $roleArg = $this->option('role');
        $rolesToTest = [];

        if (in_array($roleArg, ['super-admin', 'all'])) {
            $rolesToTest['Super Admin'] = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first() 
                ?? User::where('is_super_admin', true)->first();
        }

        if (in_array($roleArg, ['isp-admin', 'all'])) {
            $rolesToTest['ISP Admin'] = User::whereNotNull('tenant_id')
                ->whereHas('roles', fn($q) => $q->where('name', 'Admin'))
                ->first();
        }

        if (in_array($roleArg, ['reseller', 'all'])) {
            $resellerUser = User::whereHas('roles', fn($q) => $q->where('name', 'Reseller'))->first();
            if ($resellerUser) {
                $rolesToTest['Reseller'] = $resellerUser;
            }
        }

        if (in_array($roleArg, ['guest', 'all'])) {
            $rolesToTest['Guest'] = null;
        }

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($r) => in_array('GET', $r->methods()))
            ->filter(fn($r) => !str_contains($r->uri(), '{'))
            ->filter(fn($r) => !str_starts_with($r->uri(), '_'))
            ->filter(fn($r) => !str_starts_with($r->uri(), 'sanctum/'))
            ->filter(fn($r) => !str_starts_with($r->uri(), 'telescope'))
            ->filter(fn($r) => !str_starts_with($r->uri(), 'pulse'))
            ->pluck('uri')
            ->unique()
            ->values();

        $app = app();
        $totalTested = 0;
        $failed = [];

        foreach ($rolesToTest as $roleName => $user) {
            $this->line("\n<fg=cyan>=== Testing as {$roleName}" . ($user ? " (User #{$user->id}: {$user->name}, Tenant #{$user->tenant_id})" : "") . " ===</>");

            Auth::shouldUse('web');
            $store = session()->driver();
            $store->start();

            if ($user) {
                Auth::guard('web')->setUser($user);
                $store->put('login_web_' . sha1('Illuminate\Auth\SessionGuard'), $user->id);
                if ($user->tenant_id) {
                    $store->put('tenant_id', $user->tenant_id);
                }
            } else {
                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }
            }

            foreach ($routes as $uri) {
                $totalTested++;
                Auth::shouldUse('web');
                $req = Request::create("https://isp.hsmart.co.ke/" . ltrim($uri, '/'), 'GET');
                if ($user) {
                    Auth::guard('web')->setUser($user);
                    $req->setUserResolver(fn() => $user);
                }
                $req->setLaravelSession($store);

                try {
                    $response = $app->handle($req);
                    $code = $response->getStatusCode();

                    if ($code >= 500) {
                        $failed[] = [
                            'role' => $roleName,
                            'uri' => $uri,
                            'status' => $code,
                            'error' => substr(strip_tags($response->getContent()), 0, 150)
                        ];
                        $this->line("  <fg=red>FAIL</> /{$uri} [{$code}]");
                    } else {
                        $this->line("  <fg=green>PASS</> /{$uri} [{$code}]");
                    }
                } catch (\Throwable $e) {
                    $failed[] = [
                        'role' => $roleName,
                        'uri' => $uri,
                        'status' => 500,
                        'error' => $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine()
                    ];
                    $this->line("  <fg=red>EXCEPTION</> /{$uri} -> " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info("Total checks completed: {$totalTested}");

        if (count($failed) > 0) {
            $this->error("Smoke test found " . count($failed) . " failing routes!");
            foreach ($failed as $f) {
                $this->line("  - [{$f['role']}] /{$f['uri']} => {$f['status']} ({$f['error']})");
            }
            return 1;
        }

        $this->info("All routes passed cleanly with zero 5xx server errors!");
        return 0;
    }
}
