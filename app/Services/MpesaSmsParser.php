<?php

namespace App\Services;

class MpesaSmsParser
{
    /**
     * Parse incoming M-Pesa SMS text into structured parameters.
     *
     * @param string $message
     * @return array|null
     */
    public static function parse(string $message): ?array
    {
        $message = trim($message);
        if (empty($message)) {
            return null;
        }

        // 1. Transaction Code (Standard M-Pesa code: 10 alphanumeric characters at start)
        $trxId = null;
        if (preg_match('/^([A-Z0-9]{10})\s+Confirmed/i', $message, $m)) {
            $trxId = strtoupper($m[1]);
        } elseif (preg_match('/\b([A-Z0-9]{10})\b\s*(?:Confirmed|received)/i', $message, $m)) {
            $trxId = strtoupper($m[1]);
        }

        if (! $trxId) {
            return null;
        }

        // 2. Amount Extraction (Ksh, Ksh., KES, KSH followed by numbers with optional commas/decimals)
        $amount = null;
        if (preg_match('/(?:Ksh\.?|KES)\s*([\d,]+(?:\.\d{1,2})?)/i', $message, $m)) {
            $cleanAmount = str_replace(',', '', $m[1]);
            $amount = (float) $cleanAmount;
        }

        if (! $amount || $amount <= 0) {
            return null;
        }

        // 3. Sender Mobile Number
        $senderMobile = null;
        // Case: "from JOHN DOE 0712345678" or "from 254712345678 - JOHN DOE"
        if (preg_match('/\b(254[17]\d{8}|0[17]\d{8})\b/', $message, $m)) {
            $senderMobile = normalizeKenyanPhone($m[1]);
        }

        // 4. Account Reference (Paybill)
        $accountReference = null;
        if (preg_match('/(?:for\s+account|for\s+acc\.?|acc\.?|account)\s+([a-zA-Z0-9_\-\.]+)/i', $message, $m)) {
            $accountReference = trim($m[1]);
            // Strip trailing punctuation
            $accountReference = rtrim($accountReference, '.,;');
        }

        // 5. Sender Name
        $senderName = null;
        if (preg_match('/(?:from|received\s+from)\s+(?:(?:\+?254|0)[17]\d{8}\s+[-–]?\s*)?([A-Za-z\s\.\'\-]+?)(?:\s+(?:\+?254|0)[17]\d{8}|\s+on\s+\d|\s+for\s+account|\.|\,|$)/i', $message, $m)) {
            $extracted = trim($m[1]);
            // Exclude common transaction keywords from sender name
            if (! preg_match('/^(Ksh|KES|Confirmed|for|account|to)\b/i', $extracted)) {
                $senderName = $extracted;
            }
        }

        return [
            'transaction_code' => $trxId,
            'amount' => $amount,
            'phone' => $senderMobile,
            'sender_name' => $senderName,
            'account_reference' => $accountReference,
            'raw_message' => $message,
        ];
    }
}
