<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsReseller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Check if user has the reseller relation and status is active
        if (!$user->reseller || !$user->reseller->isActive()) {
            if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
                return redirect()->route('dashboard')->with('error', 'You must be logged in as a Reseller to access the Reseller Portal.');
            }

            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your reseller account is suspended or not initialized.');
        }

        return $next($request);
    }
}
