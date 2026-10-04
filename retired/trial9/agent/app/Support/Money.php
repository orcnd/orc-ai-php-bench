<?php
declare(strict_types=1);
namespace App\Support;

final class Money
{
    /** Parse a decimal amount ("19.99") from CSV imports and payment gateways into cents. */
    public static function fromDecimal(string $value): int
    {
        return (int) round(round((float) $value, 2) * 100);
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . number_format(abs($cents) / 100, 2, '.', ',');
    }
}
