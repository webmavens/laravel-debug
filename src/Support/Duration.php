<?php

namespace Webmavens\DebugMonitor\Support;

/** Formats run durations for display: "640 ms", "1.53 s". */
class Duration
{
    public static function format(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return $milliseconds.' ms';
        }

        return rtrim(rtrim(number_format($milliseconds / 1000, 2, '.', ''), '0'), '.').' s';
    }
}
