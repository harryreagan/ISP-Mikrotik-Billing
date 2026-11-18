<?php

namespace App\Services;

class MikrotikDuration
{
    /**
     * Parse any human duration, package validity, or unit specification into:
     * - 'seconds': Total integer seconds
     * - 'mikrotik': Valid MikroTik interval string (e.g. 30m, 1h, 24h, 7d, 30d)
     * - 'human': Clean human-readable text (e.g. 30 Minutes, 1 Hour, 1 Day)
     *
     * Never outputs invalid fractional units like '0.5h'.
     */
    public static function parse($duration, ?string $unit = null): array
    {
        $seconds = 0;
        $str = strtolower(trim((string)$duration));

        if (is_numeric($duration) && !empty($unit)) {
            $val = (float) $duration;
            $u = strtolower(trim($unit));
            if (str_starts_with($u, 'min') || $u === 'm') {
                $seconds = (int) round($val * 60);
            } elseif (str_starts_with($u, 'hour') || str_starts_with($u, 'hr') || $u === 'h') {
                $seconds = (int) round($val * 3600);
            } elseif (str_starts_with($u, 'day') || $u === 'd') {
                $seconds = (int) round($val * 86400);
            } elseif (str_starts_with($u, 'week') || $u === 'w') {
                $seconds = (int) round($val * 604800);
            } elseif (str_starts_with($u, 'month')) {
                $seconds = (int) round($val * 2592000);
            } else {
                $seconds = (int) round($val);
            }
        } elseif (is_numeric($duration) && empty($unit)) {
            $val = (float) $duration;
            // If < 24 assume hours, else if large assume seconds
            if ($val <= 24) {
                $seconds = (int) round($val * 3600);
            } else {
                $seconds = (int) round($val);
            }
        } else {
            // String parsing (e.g. "30 mins", "30 minutes", "1 hour", "0.5 hr", "2 days", "1 week")
            if (preg_match('/([0-9.]+)\s*(min|minute|m\b)/i', $str, $matches)) {
                $seconds = (int) round(((float)$matches[1]) * 60);
            } elseif (preg_match('/([0-9.]+)\s*(hour|hr|h\b)/i', $str, $matches)) {
                $seconds = (int) round(((float)$matches[1]) * 3600);
            } elseif (preg_match('/([0-9.]+)\s*(day|d\b)/i', $str, $matches)) {
                $seconds = (int) round(((float)$matches[1]) * 86400);
            } elseif (preg_match('/([0-9.]+)\s*(week|wk|w\b)/i', $str, $matches)) {
                $seconds = (int) round(((float)$matches[1]) * 604800);
            } elseif (preg_match('/([0-9.]+)\s*(month|mon)/i', $str, $matches)) {
                $seconds = (int) round(((float)$matches[1]) * 2592000);
            } elseif (preg_match('/([0-9.]+)\s*(sec|second|s\b)/i', $str, $matches)) {
                $seconds = (int) round((float)$matches[1]);
            } else {
                // Fallback numeric extract
                $num = (float) filter_var($str, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($num > 0) {
                    if (str_contains($str, 'min')) {
                        $seconds = (int) round($num * 60);
                    } else {
                        $seconds = (int) round($num * 3600);
                    }
                } else {
                    $seconds = 3600; // 1 hour default
                }
            }
        }

        if ($seconds <= 0) {
            $seconds = 1800; // 30 mins fallback
        }

        return [
            'seconds'  => $seconds,
            'mikrotik' => self::secondsToMikrotik($seconds),
            'human'    => self::secondsToHuman($seconds),
        ];
    }

    /**
     * Convert integer seconds into standard MikroTik RouterOS time format.
     * Guaranteed integer units without decimals.
     * Examples: 1800 -> "30m", 3600 -> "1h", 7200 -> "2h", 86400 -> "1d".
     */
    public static function secondsToMikrotik(int $seconds): string
    {
        if ($seconds <= 0) {
            return '30m';
        }

        if ($seconds % 86400 === 0) {
            return ($seconds / 86400) . 'd';
        }
        if ($seconds % 3600 === 0) {
            return ($seconds / 3600) . 'h';
        }
        if ($seconds % 60 === 0) {
            return ($seconds / 60) . 'm';
        }

        // If composite (e.g. 1h30m)
        $d = intdiv($seconds, 86400);
        $rem = $seconds % 86400;
        $h = intdiv($rem, 3600);
        $rem = $rem % 3600;
        $m = intdiv($rem, 60);
        $s = $rem % 60;

        $parts = '';
        if ($d > 0) $parts .= "{$d}d";
        if ($h > 0) $parts .= "{$h}h";
        if ($m > 0) $parts .= "{$m}m";
        if ($s > 0) $parts .= "{$s}s";

        return !empty($parts) ? $parts : "{$seconds}s";
    }

    /**
     * Clean human text representation.
     */
    public static function secondsToHuman(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} Seconds";
        }
        if ($seconds < 3600) {
            $m = (int) round($seconds / 60);
            return "{$m} " . ($m === 1 ? 'Minute' : 'Minutes');
        }
        if ($seconds < 86400) {
            $h = (int) round($seconds / 3600);
            return "{$h} " . ($h === 1 ? 'Hour' : 'Hours');
        }
        $d = (int) round($seconds / 86400);
        return "{$d} " . ($d === 1 ? 'Day' : 'Days');
    }
}

