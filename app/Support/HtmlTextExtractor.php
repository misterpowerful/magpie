<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;

/**
 * Strips markup noise (scripts, nav/footer chrome) and returns visible
 * text, used both by enrichment (to get a listing's full body) and to
 * judge whether a fetched page is actually an empty JS-rendered shell
 * (spec section 5 "enrichment escalation").
 */
final class HtmlTextExtractor
{
    private const STRIP_TAGS = ['script', 'style', 'nav', 'footer', 'header', 'noscript', 'svg'];

    public function extractText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previousSetting = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previousSetting);

        foreach (self::STRIP_TAGS as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);

            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node?->parentNode?->removeChild($node);
            }
        }

        $text = $dom->textContent ?? '';
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/ *\n *(?: *\n)*/', "\n\n", $text) ?? '';

        return trim($text);
    }
}
