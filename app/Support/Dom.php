<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;

/**
 * Lenient DOM loading for markup Magpie doesn't control (job pages,
 * career sites, uploaded .docx files): real-world HTML is rarely valid,
 * and libxml's warnings about it are noise, not errors.
 */
final class Dom
{
    public static function fromHtml(string $html): DOMDocument
    {
        return self::quietly(fn (DOMDocument $dom) => $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING));
    }

    /** LIBXML_NONET: never fetch external entities/DTDs referenced by the file. */
    public static function fromXml(string $xml): DOMDocument
    {
        return self::quietly(fn (DOMDocument $dom) => $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
    }

    /**
     * @param  callable(DOMDocument): mixed  $load
     */
    private static function quietly(callable $load): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;

        try {
            $load($dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }
}
