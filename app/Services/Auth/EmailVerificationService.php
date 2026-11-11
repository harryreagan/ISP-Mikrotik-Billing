<?php

namespace App\Services\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    const CODE_EXPIRY_SECONDS = 600;       // 10 minutes
    const RESEND_COOLDOWN_SECONDS = 60;    // 60 seconds
    const HOURLY_MAX_RESENDS = 5;          // 5 per hour
    const MAX_INCORRECT_ATTEMPTS = 5;      // 5 attempts per code

    /**
     * Generate a cryptographically secure 6-digit code, store hashed, and send via email.
     * Never logs the code.
     */
    public function generateAndSendCode(string $email, string $ip, ?string $phone = null): array
    {
        $normalizedEmail = strtolower(trim($email));

        // 1. Check if email belongs to an already verified account
        $user = User::where('email', $normalizedEmail)->first();
        if ($user && $user->email_verified_at) {
            return [
                'status' => 'already_registered',
                'message' => 'This email address is already registered. Please sign in.',
            ];
        }

        $emailKey = md5($normalizedEmail);
        $ipKey = md5($ip);

        // 2. Check 60-second cooldown
        $cooldownKey = "verify_cooldown:{$emailKey}";
        if (Cache::has($cooldownKey)) {
            $secondsLeft = Cache::get("verify_cooldown_expiry:{$emailKey}", 60) - now()->timestamp;
            $secondsLeft = max(1, $secondsLeft);
            return [
                'status' => 'cooldown',
                'message' => "Please wait {$secondsLeft} second(s) before requesting a new code.",
            ];
        }

        // 3. Check hourly limit per email and IP (max 5 per hour)
        $hourlyEmailKey = "verify_hourly_email:{$emailKey}";
        $hourlyEmailCount = (int) Cache::get($hourlyEmailKey, 0);
        if ($hourlyEmailCount >= self::HOURLY_MAX_RESENDS) {
            return [
                'status' => 'rate_limited',
                'message' => 'Too many verification requests. Please try again in an hour.',
            ];
        }

        $hourlyIpKey = "verify_hourly_ip:{$ipKey}";
        $hourlyIpCount = (int) Cache::get($hourlyIpKey, 0);
        if ($hourlyIpCount >= (self::HOURLY_MAX_RESENDS * 2)) {
            return [
                'status' => 'rate_limited',
                'message' => 'Too many verification requests from your network. Please try again later.',
            ];
        }

        // 4. Generate cryptographically secure 6-digit code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hashedCode = hash('sha256', $code);

        // 5. Store hashed code only in cache (10 min expiry)
        $cacheKey = "verify_code:{$emailKey}";
        Cache::put($cacheKey, [
            'hash' => $hashedCode,
            'attempts' => 0,
            'created_at' => now()->timestamp,
        ], self::CODE_EXPIRY_SECONDS);

        // 6. Set cooldown and increment hourly counters
        Cache::put($cooldownKey, true, self::RESEND_COOLDOWN_SECONDS);
        Cache::put("verify_cooldown_expiry:{$emailKey}", now()->addSeconds(self::RESEND_COOLDOWN_SECONDS)->timestamp, self::RESEND_COOLDOWN_SECONDS);

        if (!Cache::has($hourlyEmailKey)) {
            Cache::put($hourlyEmailKey, 1, 3600);
        } else {
            Cache::increment($hourlyEmailKey);
        }

        if (!Cache::has($hourlyIpKey)) {
            Cache::put($hourlyIpKey, 1, 3600);
        } else {
            Cache::increment($hourlyIpKey);
        }

        // 7. Dispatch branded email immediately (synchronous delivery)
        try {
            Mail::to($normalizedEmail)->send(new VerificationCodeMail($code));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("[EmailVerification] Failed to send verification email to {$normalizedEmail}: " . $e->getMessage());
        }

        // 8. Dispatch real SMS OTP immediately if mobile phone provided
        $smsSent = false;
        if (!empty($phone)) {
            try {
                $cleanPhone = \App\Services\Auth\OtpVerificationService::normalizeKenyanPhone($phone);
                if ($cleanPhone) {
                    $siteName = siteUrlSettings('site_name') ?? 'HSmart WiFi';
                    $smsMsg = "Your {$siteName} verification code is: {$code}. Valid for 10 minutes. Welcome to HSmart!";
                    $smsSent = \App\Services\Sms\IspSmsDispatcher::sendDirectSms($cleanPhone, $smsMsg, null);
                }
            } catch (\Throwable $se) {
                \Illuminate\Support\Facades\Log::warning("[EmailVerification] Failed to send verification SMS to {$phone}: " . $se->getMessage());
            }
        }

        $destText = $smsSent ? 'email and SMS' : 'email address';

        return [
            'status' => 'sent',
            'message' => "A 6-digit verification code has been sent to your {$destText}.",
        ];
    }

    /**
     * Validate user-supplied code against stored hash.
     */
    public function verifyCode(string $email, string $inputCode): array
    {
        $normalizedEmail = strtolower(trim($email));
        $emailKey = md5($normalizedEmail);
        $cacheKey = "verify_code:{$emailKey}";

        $stored = Cache::get($cacheKey);

        // Check if code exists and is not expired
        if (!$stored) {
            return [
                'status' => 'expired',
                'message' => 'The verification code has expired, already been used, or does not exist. Please request a new code.',
            ];
        }

        // Check attempt count
        if ($stored['attempts'] >= self::MAX_INCORRECT_ATTEMPTS) {
            Cache::forget($cacheKey);
            return [
                'status' => 'exceeded',
                'message' => 'Too many incorrect attempts. For security, this code has been revoked. Please request a new code.',
            ];
        }

        // Constant-time hash comparison
        $cleanInput = preg_replace('/\D/', '', $inputCode);
        $inputHash = hash('sha256', $cleanInput);

        if (!hash_equals($stored['hash'], $inputHash)) {
            $stored['attempts']++;
            $remaining = self::MAX_INCORRECT_ATTEMPTS - $stored['attempts'];

            if ($remaining <= 0) {
                Cache::forget($cacheKey);
                return [
                    'status' => 'exceeded',
                    'message' => 'Too many incorrect attempts. This code has been revoked. Please request a new code.',
                ];
            }

            Cache::put($cacheKey, $stored, self::CODE_EXPIRY_SECONDS);
            return [
                'status' => 'invalid',
                'message' => "Invalid verification code. You have {$remaining} attempt(s) remaining.",
            ];
        }

        // Code is correct: consume immediately to prevent reuse
        Cache::forget($cacheKey);

        // Mark user email as verified
        $user = User::where('email', $normalizedEmail)->first();
        if ($user) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        return [
            'status' => 'success',
            'user' => $user,
            'message' => 'Your email address has been successfully verified.',
        ];
    }
}

