<?php
declare(strict_types=1);
namespace App\Pdf;

use App\Support\Money;

final class TaxLine
{
    /** Tax column on the invoice PDF; '–' when the line has no tax at all (null). */
    public function render(?int $taxCents): string
    {
        if ($taxCents === null) {
            return '–';
        }
        return Money::format($taxCents);
    }
}
