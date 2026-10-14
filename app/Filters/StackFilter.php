<?php

namespace App\Filters;

/**
 * Spec section 6 "Stack": must-have PHP/Laravel signals pass outright;
 * a hard, closed requirement for a different stack (with no PHP/Laravel
 * mention anywhere) fails. Soft language ("Java or similar") is left
 * UNKNOWN, not failed, and passes through to scoring.
 */
class StackFilter
{
    private const PHP_SIGNALS = '/\b(PHP|Laravel|Symfony|WordPress|Drupal|CodeIgniter|CakePHP)\b/i';

    private const HARD_REQUIREMENT_PHRASE = '/\b(must\s*have|required|requires|minimum\s*of|at\s*least)\b[^.]{0,40}?\b(Java(?:Script)?|\.NET|C#|Python|Ruby(?:\s*on\s*Rails)?|Rails|Go(?:lang)?|Node\.?js)\b/i';

    private const SOFTENER = '/\bor\s*(similar|equivalent)\b/i';

    public function check(string $text): FilterResult
    {
        $hasPhpSignal = preg_match(self::PHP_SIGNALS, $text) === 1;

        if ($hasPhpSignal) {
            return FilterResult::pass();
        }

        if (preg_match_all(self::HARD_REQUIREMENT_PHRASE, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                [$matchText, $offset] = $match;
                $after = substr($text, $offset + strlen($matchText), 20);

                if (! preg_match(self::SOFTENER, $after)) {
                    return FilterResult::fail("Hard requirement for a non-PHP stack: \"{$matchText}\"");
                }
            }
        }

        return FilterResult::unknown();
    }
}
