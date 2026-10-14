<?php

namespace App\Support;

/**
 * Spec section 7: "Trimmed input: role, requirements, and compensation
 * sections only. Strip benefits and legal boilerplate." This drops
 * paragraphs that are confidently boilerplate (EEO statements, benefits
 * lists, visa/legal disclaimers) rather than trying to positively
 * identify "the requirements section" — company/mission description is
 * deliberately kept, since "mission/stability alignment" is a scoring
 * criterion that needs it.
 */
class ListingTextTrimmer
{
    private const BOILERPLATE_PARAGRAPH = '/\b(equal\s*opportunity\s*employer|EEOC?\b|we\s*are\s*committed\s*to\s*diversity|background\s*check|drug\s*test|visa\s*sponsorship|not\s*able\s*to\s*sponsor|E-?Verify|reasonable\s*accommodation|privacy\s*policy|applicant\s*privacy|pursuant\s*to\s*the\s*.*?\bact\b)/i';

    private const BOILERPLATE_HEADER = '/^\s*(benefits|perks|what\s*we\s*offer|our\s*benefits|equal\s*opportunity|eeo\s*statement|diversity\s*(and|&)\s*inclusion|legal|disclaimer|privacy)\s*:?\s*$/i';

    public function trim(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $kept = [];
        $skippingSection = false;

        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match(self::BOILERPLATE_HEADER, $trimmed)) {
                $skippingSection = true;

                continue;
            }

            if ($skippingSection) {
                // A short, title-like line starts a new (non-boilerplate)
                // section; anything else is assumed to still belong to
                // the boilerplate section we're skipping.
                if (str_word_count($trimmed) <= 6 && ! str_contains($trimmed, '.')) {
                    $skippingSection = false;
                } else {
                    continue;
                }
            }

            if (preg_match(self::BOILERPLATE_PARAGRAPH, $trimmed)) {
                continue;
            }

            $kept[] = $trimmed;
        }

        return implode("\n\n", $kept);
    }
}
