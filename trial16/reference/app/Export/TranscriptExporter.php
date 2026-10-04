<?php
declare(strict_types=1);
namespace App\Export;

/**
 * Exports transcribed documents. Transcribers mark a word split across two
 * lines with "¬" at the end of the first line ("Ver¬\nwaltung").
 */
final class TranscriptExporter
{
    public const VERBATIM = 'verbatim';
    public const READING = 'reading';

    /**
     * VERBATIM reproduces the page line by line, as transcribed, for scholars.
     * READING is flowing text: line breaks inside a paragraph become spaces;
     * blank lines separate paragraphs.
     */
    public function export(string $transcript, string $mode): string
    {
        $text = str_replace("\r\n", "\n", $transcript);
        if ($mode === self::VERBATIM) {
            return $text;
        }
        $text = (string) preg_replace("/¬[ \t]*\n[ \t]*/u", '', $text);
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
        return implode("\n\n", array_map(function (string $p): string {
            return (string) preg_replace("/\s*\n\s*/", ' ', trim($p));
        }, $paragraphs));
    }
}
