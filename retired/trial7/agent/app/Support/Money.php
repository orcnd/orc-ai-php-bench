<?php
declare(strict_types=1);
namespace App\Support;

final class Money
{
    /**
     * Parse a decimal amount ("19.99") from CSV imports and payment gateways
     * into integer cents.
     *
     * Rounds to 2 decimals first and only then scales. PHP's round() applies
     * pre-rounding, so amounts typed by humans such as "1.005" or "0.285"
     * round up the way finance's spreadsheets do. A single round($v * 100) or
     * an (int) cast loses a cent on those values. See docs/adr/0003.
     */
    public static function fromDecimal(string $value): int
    {
        return (int) round(round((float) $value, 2) * 100);
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . number_format(abs($cents) / 100, 2, '.', ',');
    }
}
