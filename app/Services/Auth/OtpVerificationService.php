<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Sms\IspSmsDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpVerificationService
{
    const OTP_EXPIRY_MINUTES = 5;
    const RESEND_COOLDOWN_SECONDS = 60;
    const MAX_HOURLY_ATTEMPTS = 5;
    const MAX_INCORRECT_ATTEMPTS = 5;

    /**
     * Normalize Kenyan phone number to 2547XXXXXXXX or 2541XXXXXXXX format.
     */
    public static function normalizeKenyanPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        // 07XXXXXXXX or 01XXXXXXXX (10 digits) -> 2547XXXXXXXX / 2541XXXXXXXX
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254' . substr($digits, 1);
        }

        // 7XXXXXXXX or 1XXXXXXXX (9 digits) -> 2547XXXXXXXX / 2541XXXXXXXX
        if ((str_starts_with($digits, '7') || str_starts_with($digits, '1')) && strlen($digits) === 9) {
            return '254' . $digits;
        }

        // 2547XXXXXXXX or 2541XXXXXXXX (12 digits)
        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Send OTP for phone sign-up, OTP login, or password reset.
     */
    public static function generateAndSendOtp(string $destination, string $actionType = 'auth', ?int $tenantId = null, ?string $ip = null): array
    {
        $isEmail = str_contains($destination, '@');
        $normalized = $isEmail ? strtolower(trim($destination)) : self::normalizeKenyanPhone($destination);

        if (!$normalized) {
            return ['status' => 'error', 'message' => 'Invalid phone number or email address provided.'];
        }

        $destKey = md5($normalized . '_' . $actionType);

        // 1. Check Cooldown
        $cooldownKey = "otp_cooldown_{$destKey}";
        if (Cache::has($cooldownKey)) {
            $left = Cache::get("otp_cooldown_expiry_{$destKey}", 60) - now()->timestamp;
            $left = max(1, $left);
            return ['status' => 'cooldown', 'message' => "Please wait {$left} second(s) before requesting a new OTP."];
        }

        // 2. Check Hourly Limit
        $hourlyKey = "otp_hourly_{$destKey}";
        $hourlyCount = (int) Cache::get($hourlyKey, 0);
        if ($hourlyCount >= self::MAX_HOURLY_ATTEMPTS) {
            return ['status' => 'rate_limited', 'message' => 'Too many OTP requests. Please try again in 1 hour.'];
        }

        // 3. Generate 6-digit cryptographically secure code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $challengeId = (string) Str::uuid();

        // 4. Save to login_otp_codes table
        DB::table('login_otp_codes')->insert([
            'challenge_id'   => $challengeId,
            'email'          => $isEmail ? $normalized : null,
            'phone'          => !$isEmail ? $normalized : null,
            'action_type'    => $actionType,
            'code_hash'      => Hash::make($code),
            'attempt_count'  => 0,
            'expires_at'     => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'ip_address'     => $ip ?: request()->ip(),
            'user_agent'     => request()->userAgent(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // 5. Dispatch message via SMS or Email
        $message = "Your HSmart verification code is: {$code}. Valid for 5 minutes. Never share this code with anyone.";
        $dispatched = false;

        if ($isEmail) {
            try {
                \Illuminate\Support\Facades\Mail::to($normalized)->send(new \App\Mail\VerificationCodeMail($code));
                $dispatched = true;
            } catch (\Throwable $e) {
                Log::error("[OtpService] Email delivery failed: " . $e->getMessage());
            }
        } else {
            $dispatched = IspSmsDispatcher::sendDirectSms($normalized, $message, $tenantId);
        }

        // 6. Record rate limiting caches
        Cache::put($cooldownKey, true, self::RESEND_COOLDOWN_SECONDS);
        Cache::put("otp_cooldown_expiry_{$destKey}", now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp, self::RESEND_COOLDOWN_SECONDS);
        Cache::put($hourlyKey, $hourlyCount + 1, 3600);

        return [
            'status'       => 'sent',
            'challenge_id' => $challengeId,
            'destination'  => $normalized,
            'type'         => $isEmail ? 'email' : 'phone',
            'message'      => 'A 6-digit verification code has been sent to ' . ($isEmail ? $normalized : substr($normalized, 0, 4) . '****' . substr($normalized, -3)) . '.',
        ];
    }

    /**
     * Verify 6-digit OTP code with attempt lockout.
     */
    public static function verifyOtp(string $destination, string $code, string $actionType = 'auth'): array
    {
        $isEmail = str_contains($destination, '@');
        $normalized = $isEmail ? strtolower(trim($destination)) : self::normalizeKenyanPhone($destination);
        $cleanCode = preg_replace('/\D/', '', $code);

        if (strlen($cleanCode) !== 6) {
            return ['status' => 'invalid_format', 'message' => 'Please enter the complete 6-digit verification code.'];
        }

        $query = DB::table('login_otp_codes')
            ->whereNull('consumed_at')
            ->where('action_type', $actionType)
            ->where('expires_at', '>', now())
            ->orderBy('id', 'desc');

        if ($isEmail) {
            $query->where('email', $normalized);
        } else {
            $query->where('phone', $normalized);
        }

        $record = $query->first();

        if (!$record) {
            return ['status' => 'expired', 'message' => 'Verification code has expired or was not found. Please request a new code.'];
        }

        if ($record->attempt_count >= self::MAX_INCORRECT_ATTEMPTS) {
            return ['status' => 'locked', 'message' => 'Too many failed attempts. This code has been locked. Please request a new code.'];
        }

        if (!Hash::check($cleanCode, $record->code_hash)) {
            DB::table('login_otp_codes')->where('id', $record->id)->increment('attempt_count');
            $remaining = self::MAX_INCORRECT_ATTEMPTS - ($record->attempt_count + 1);
            return [
                'status' => 'incorrect',
                'message' => "Incorrect code. {$remaining} attempt(s) remaining.",
            ];
        }

        // Mark consumed
        DB::table('login_otp_codes')->where('id', $record->id)->update([
            'consumed_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'status' => 'verified',
            'destination' => $normalized,
            'type' => $isEmail ? 'email' : 'phone',
            'message' => 'Verification successful.',
        ];
    }
}

