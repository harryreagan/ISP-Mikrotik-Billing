<?php

use App\Http\Controllers\AvatarController;
use App\Models\MainSiteData;

/**
 * Created by Md. Jahangir Alam Rohan.
 * User: Md. Jahangir Alam Rohan.
 * Date: 25-Jun-2024
 * Time: 03.01 PM
 */

if (! function_exists('siteUrlSettings')) {
    function siteUrlSettings($key, $fresh = false)
    {
        static $hasTable = null;
        static $runtimeCache = [];

        if ($fresh || app()->environment('testing')) {
            $hasTable = null;
            $runtimeCache = [];
        }

        try {
            if ($hasTable === null || $hasTable === false) {
                $hasTable = \Illuminate\Support\Facades\Schema::hasTable('main_site_data');
            }
            if (! $hasTable) {
                return null;
            }

            $tenantId = \App\Services\Tenant\TenantContext::getTenantId() ?? 0;
            $cacheKey = $tenantId . ':' . $key;

            if (array_key_exists($cacheKey, $runtimeCache)) {
                return $runtimeCache[$cacheKey];
            }

            return $runtimeCache[$cacheKey] = \App\Models\MainSiteData::getValue($key);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (! function_exists('generate_avatar')) {
    function generate_avatar($name)
    {
        $controller = app(AvatarController::class);

        return $controller->generateAvatar($name);
    }
}

if (! function_exists('hasAccess')) {
    function hasAccess(array $roles = [], array $permissions = []): bool
    {
        $user = auth()->user();

        return $user && (
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) ||
            (method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions))
        );
    }
}

if (! function_exists('abortIfNoAccess')) {
    function abortIfNoAccess(array $roles = [], array $permissions = [], string $message = 'You do not have permission.'): bool
    {
        if (! hasAccess($roles, $permissions)) {
            flash()->error($message);

            return true;
        }

        return false;
    }
}

if (! function_exists('warningIfNoAccess')) {
    function warningIfNoAccess(array $roles = [], array $permissions = [], string $message = 'You do not have permission.'): bool
    {
        if (! hasAccess($roles, $permissions)) {
            flash()->warning($message);

            return true;
        }

        return false;
    }
}

if (! function_exists('site_image')) {
    /**
     * Correctly resolve site images from public or storage.
     * Handles both string paths and Filament FileUpload array values.
     */
    function site_image($path, $fallback = 'images/logo.png')
    {
        // Filament FileUpload sometimes returns an array — unwrap it
        if (is_array($path)) {
            $path = array_values(array_filter($path))[0] ?? null;
        }

        if (! $path || ! is_string($path)) {
            return asset($fallback);
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Path clean up
        $path = ltrim($path, '/');

        // Check 1: public folder
        if (file_exists(public_path($path))) {
            return asset($path);
        }

        // Check 2: storage folder (standard Filament/Laravel location)
        if (file_exists(public_path('storage/'.$path))) {
            return asset('storage/'.$path);
        }

        // Final fallback: asset with original path
        return asset($path);
    }
}

if (! function_exists('normalizeKenyanPhone')) {
    /**
     * Normalize a phone number to standard Kenyan format (2547XXXXXXXX or 2541XXXXXXXX).
     */
    function normalizeKenyanPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Strip non-digit characters
        $digits = preg_replace('/\D+/', '', $phone);

        // 07XXXXXXXX or 01XXXXXXXX (10 digits) -> 2547XXXXXXXX / 2541XXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        }

        // 7XXXXXXXX or 1XXXXXXXX (9 digits) -> 2547XXXXXXXX / 2541XXXXXXXX
        if (strlen($digits) === 9 && (str_starts_with($digits, '7') || str_starts_with($digits, '1'))) {
            $digits = '254' . $digits;
        }

        // 25407XXXXXXXX or 25401XXXXXXXX (13 digits) -> 2547XXXXXXXX / 2541XXXXXXXX
        if (strlen($digits) === 13 && (str_starts_with($digits, '25407') || str_starts_with($digits, '25401'))) {
            $digits = '254' . substr($digits, 4);
        }

        return $digits;
    }
}

if (! function_exists('format_currency')) {
    function format_currency($amount, $currency = 'KES'): string
    {
        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

