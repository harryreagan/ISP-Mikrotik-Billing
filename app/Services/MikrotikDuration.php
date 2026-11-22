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
            // 0. RouterOS colon-based duration/uptime parsing (e.g. "00:05:00", "01:30:15", "05:30", "1d02:15:30", "2w1d04:20:00")
            if (preg_match('/^(([0-9]+)\s*w)?\s*(([0-9]+)\s*d)?\s*([0-9]{1,2}:[0-9]{2}(:[0-9]{2})?)$/i', $str, $cm)) {
                $weeks = !empty($cm[2]) ? (int) $cm[2] : 0;
                $days = !empty($cm[4]) ? (int) $cm[4] : 0;
                $timePart = $cm[5];
                $timeParts = explode(':', $timePart);
                if (count($timeParts) === 3) {
                    $h = (int) $timeParts[0];
                    $m = (int) $timeParts[1];
                    $s = (int) $timeParts[2];
                } else {
                    $h = 0;
                    $m = (int) $timeParts[0];
                    $s = (int) $timeParts[1];
                }
                $seconds = ($weeks * 604800) + ($days * 86400) + ($h * 3600) + ($m * 60) + $s;
            } else {
                // 1. Compound MikroTik duration parsing (e.g. "1w2d3h4m5s", "1h34m30s", "45m", "30s")
                $compoundMatched = false;
                $totalCompound = 0;
                if (preg_match_all('/([0-9.]+)\s*([wdhms])/i', $str, $allParts, PREG_SET_ORDER)) {
                    foreach ($allParts as $part) {
                        $val = (float) $part[1];
                        $unitKey = strtolower($part[2]);
                        $compoundMatched = true;
                        if ($unitKey === 'w') $totalCompound += (int) round($val * 604800);
                        elseif ($unitKey === 'd') $totalCompound += (int) round($val * 86400);
                        elseif ($unitKey === 'h') $totalCompound += (int) round($val * 3600);
                        elseif ($unitKey === 'm') $totalCompound += (int) round($val * 60);
                        elseif ($unitKey === 's') $totalCompound += (int) round($val);
                    }
                }

                if ($compoundMatched && $totalCompound > 0) {
                    $seconds = $totalCompound;
                } elseif (preg_match('/([0-9.]+)\s*(month|mon)/i', $str, $matches)) {
                    $seconds = (int) round(((float)$matches[1]) * 2592000);
                } elseif (preg_match('/([0-9.]+)\s*(week|wk)/i', $str, $matches)) {
                    $seconds = (int) round(((float)$matches[1]) * 604800);
                } elseif (preg_match('/([0-9.]+)\s*(day)/i', $str, $matches)) {
                    $seconds = (int) round(((float)$matches[1]) * 86400);
                } elseif (preg_match('/([0-9.]+)\s*(hour|hr)/i', $str, $matches)) {
                    $seconds = (int) round(((float)$matches[1]) * 3600);
                } elseif (preg_match('/([0-9.]+)\s*(min|minute)/i', $str, $matches)) {
                    $seconds = (int) round(((float)$matches[1]) * 60);
                } elseif (preg_match('/([0-9.]+)\s*(sec|second)/i', $str, $matches)) {
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

