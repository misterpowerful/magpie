<?php

declare(strict_types=1);

namespace App\Support;

use DOMXPath;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Turns an uploaded resume (PDF, Word .docx, or plain text/Markdown) into
 * the plain text the scorer reads. Extraction is best-effort — the owner
 * sees the result on the Resume tab and can correct it before it's used.
 */
final class ResumeTextExtractor
{
    public const EXTENSIONS = ['pdf', 'docx', 'txt', 'md'];

    /**
     * @throws RuntimeException when the file can't be read or yields no text
     */
    public function extract(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => $this->fromPdf($path),
            'docx' => $this->fromDocx($path),
            'txt', 'md' => (string) file_get_contents($path),
            default => throw new RuntimeException("Unsupported resume file type: .{$extension}"),
        };

        $text = $this->normalize($text);

        if ($text === '') {
            throw new RuntimeException('No text could be extracted from that file. If it is a scanned PDF (an image of text), upload a .docx or .txt version instead.');
        }

        return $text;
    }

    private function fromPdf(string $path): string
    {
        try {
            return (new PdfParser)->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new RuntimeException("Couldn't read that PDF: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * A .docx is a zip; the body is word/document.xml. Paragraphs (w:p)
     * become lines, tabs and explicit breaks are kept.
     */
    private function fromDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Reading .docx files needs the PHP zip extension on the server. Upload a PDF or .txt instead.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Couldn't open that .docx file.");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException("That .docx file has no document body.");
        }

        $dom = Dom::fromXml($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $lines = [];

        foreach ($xpath->query('//w:body//w:p') ?: [] as $paragraph) {
            $line = '';

            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) ?: [] as $node) {
                $line .= match ($node->localName) {
                    'tab' => "\t",
                    'br' => "\n",
                    default => $node->textContent,
                };
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** Unix newlines, no trailing spaces, at most one blank line in a row. */
    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
