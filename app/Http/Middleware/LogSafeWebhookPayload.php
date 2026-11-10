<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\SafePayloadLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogSafeWebhookPayload
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sanitizedPayload = SafePayloadLogger::sanitize($request->all());
        $sanitizedHeaders = SafePayloadLogger::sanitize([
            'user-agent'    => $request->userAgent(),
            'ip'            => $request->ip(),
            'authorization' => $request->header('Authorization') ? 'Bearer [PRESENT]' : 'NONE',
            'signature'     => $request->header('X-KopoKopo-Signature') ?? $request->header('X-Signature') ?? 'NONE',
        ]);

        SafePayloadLogger::info('Incoming Webhook: ' . $request->path(), [
            'method'  => $request->method(),
            'headers' => $sanitizedHeaders,
            'payload' => $sanitizedPayload,
        ]);

        return $next($request);
    }
}

