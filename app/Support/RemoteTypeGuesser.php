<?php

namespace App\Support;

class RemoteTypeGuesser
{
    public static function guess(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        return match (true) {
            (bool) preg_match('/\bremote\b/i', $text) => 'remote',
            (bool) preg_match('/\bhybrid\b/i', $text) => 'hybrid',
            (bool) preg_match('/\bon-?site\b/i', $text) => 'onsite',
            default => null,
        };
    }
}
