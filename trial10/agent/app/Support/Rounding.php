<?php
declare(strict_types=1);
namespace App\Support;

/** Integer division with rounding, for money in cents. */
final class Rounding
{
    public static function halfUp(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    public static function halfDown(int $numerator, int $denominator): int
    {
        $sign = $numerator < 0 ? -1 : 1;
        $abs = abs($numerator);
        $quotient = intdiv($abs, $denominator);
        return $sign * (2 * ($abs % $denominator) > $denominator ? $quotient + 1 : $quotient);
    }
}
