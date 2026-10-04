<?php
declare(strict_types=1);
namespace App\Notebook;

/**
 * Report cells: a cell whose last statement ends with ";" does not display
 * its value (like Jupyter/MATLAB). Statements are separated by ";",
 * "#" starts a comment, strings use single or double quotes.
 */
final class Suppression
{
    public function isSuppressed(string $cell): bool
    {
        $code = '';
        foreach (preg_split("/\r?\n/", $cell) ?: [] as $line) {
            $code .= self::stripComment($line) . "\n";
        }
        return substr(rtrim($code), -1) === ';';
    }

    /** Removes a trailing "#" comment and replaces string contents, so ";" or "#" inside strings do not count. */
    private static function stripComment(string $line): string
    {
        $out = '';
        $quote = null;
        for ($i = 0, $n = strlen($line); $i < $n; $i++) {
            $c = $line[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                    $out .= 'S';
                }
                continue;
            }
            if ($c === '#') {
                break;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                continue;
            }
            $out .= $c;
        }
        return $out;
    }
}
