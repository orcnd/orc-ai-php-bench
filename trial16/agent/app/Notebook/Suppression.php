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
        return substr(rtrim($cell), -1) === ';';
    }
}
