<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;

class SafePayloadLogger
{
    /**
     * Keys whose values must be masked in logs.
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'pin',
        'otp',
        'secret',
        'token',
        'api_key',
        'apikey',
        'api_secret',
        'consumer_secret',
        'consumer_key',
        'passkey',
        'client_secret',
        'client_id',
        'webhook_secret',
        'authorization',
        'auth',
        'bearer',
        'cvv',
        'card_number',
    ];

    /**
     * Recursively sanitize array data, masking any sensitive values.
     */
    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $lowerKey = strtolower((string) $key);
                $isSensitive = false;

                foreach (self::$sensitiveKeys as $pattern) {
                    if (str_contains($lowerKey, $pattern)) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $cleaned[$key] = '[REDACTED]';
                } elseif (is_array($value) || is_string($value)) {
                    $cleaned[$key] = self::sanitize($value);
                } else {
                    $cleaned[$key] = $value;
                }
            }
            return $cleaned;
        }

        if (is_string($data)) {
            // Strip potential secret tokens from URLs
            return preg_replace('/([?&](?:api_token|token|secret|key|password)=)[^&]+/i', '$1[REDACTED]', $data);
        }

        return $data;
    }

    /**
     * Safely log an info message with redacted context.
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info(self::sanitize($message), self::sanitize($context));
    }

    /**
     * Safely log a warning message with redacted context.
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning(self::sanitize($message), self::sanitize($context));
    }

    /**
     * Safely log an error message with redacted context.
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error(self::sanitize($message), self::sanitize($context));
    }
}
