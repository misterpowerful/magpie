<?php

declare(strict_types=1);

namespace App\Inbox;

/**
 * Deterministic (regex-only, no LLM) detector/extractor for application-
 * confirmation emails ("thank you for applying to Acme Corp") — spec
 * section 9's suggested alternative to routing this through Gatsby.
 *
 * Company extraction is necessarily heuristic: ATS confirmation emails
 * don't follow one template. This covers the common phrasings; a
 * candidate that matches no listing is harmless (ApplicationConfirmationService
 * just reports "no match" rather than guessing), so a weak or wrong
 * extraction never causes an incorrect auto-apply on its own — it still
 * has to line up with an actual listing's company name.
 */
final class ApplicationConfirmationMatcher
{
    private const CONFIRMATION_SIGNAL = '/\b(thank\s*you\s*for\s*(applying|your\s*application|your\s*interest)'
        .'|we(\'|\x{2019})ve\s*received\s*your\s*application'
        .'|we\s*have\s*received\s*your\s*application'
        .'|your\s*application\s*(has\s*been|was)\s*(received|submitted)'
        .'|application\s*(received|submitted|confirmation))\b/iu';

    // The trigger phrase is matched case-insensitively (inline `(?i:...)`,
    // not a blanket `/i` on the whole pattern) so the capture group's own
    // `[A-Z]` stays meaningful — that's what stops the match at the next
    // lowercase word instead of running on. The gap before/inside the
    // captured name is restricted to `[ \t]` (no `\n`) so a capitalized
    // word starting the *next* line (e.g. "...Acme Corp\nWe have...")
    // never gets pulled into the capture.
    private const COMPANY_AFTER_PREPOSITION = '/(?i:thank\s*you\s*for\s*applying|your\s*application|interest\s*in\s*(?:the\s*)?(?:[\w ]{1,40}?[ \t]*)?(?:position|role)?)'
        .'[ \t]*(?i:to|at|with)[ \t]+([A-Z][\w&.,\'\-]*(?:[ \t]+[A-Z&][\w&.,\'\-]*){0,4})\b/u';

    private const INTEREST_IN_COMPANY = '/(?i:interest)[ \t]+(?i:in)[ \t]+([A-Z][\w&.,\'\-]*(?:[ \t]+[A-Z&][\w&.,\'\-]*){0,4})\b/u';

    private const FROM_NAME_NOISE = '/\b(careers?|recruiting|talent\s*acquisition|hiring\s*team|human\s*resources|hr|jobs?|team|no\s*-?\s*reply|notifications?)\b/i';

    public function looksLikeConfirmation(string $subject, string $bodyText): bool
    {
        return preg_match(self::CONFIRMATION_SIGNAL, $subject.' '.$bodyText) === 1;
    }

    /**
     * @return array<int, string> candidate company names, best-effort,
     *                            in no particular priority order
     */
    public function extractCompanyCandidates(string $subject, string $bodyText, string $fromDisplayName): array
    {
        $text = $subject."\n".$bodyText;
        $candidates = [];

        if (preg_match(self::COMPANY_AFTER_PREPOSITION, $text, $m)) {
            $candidates[] = trim($m[1]);
        }

        if (preg_match(self::INTEREST_IN_COMPANY, $text, $m)) {
            $candidates[] = trim($m[1]);
        }

        $fromCandidate = trim((string) preg_replace(self::FROM_NAME_NOISE, '', $fromDisplayName));
        $fromCandidate = trim($fromCandidate, " \t\n\r\0\x0B-|");

        if ($fromCandidate !== '') {
            $candidates[] = $fromCandidate;
        }

        return array_values(array_unique(array_filter($candidates, fn (string $c) => $c !== '')));
    }

    /**
     * Normalizes a company name for comparison: lowercase, collapsed
     * whitespace, and common corporate suffixes stripped, so "Acme Corp"
     * and "ACME Corporation" compare equal.
     */
    public function normalizeCompany(string $company): string
    {
        $normalized = strtolower(trim($company));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[.,]/', '', $normalized) ?? $normalized;

        return trim(preg_replace(
            '/\b(inc|incorporated|llc|l l c|corp|corporation|ltd|limited|co|company)\b/i',
            '',
            $normalized,
        ) ?? $normalized);
    }
}
