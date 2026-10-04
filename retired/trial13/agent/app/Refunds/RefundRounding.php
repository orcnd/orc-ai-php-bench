<?php
declare(strict_types=1);
namespace App\Refunds;

final class RefundRounding
{
    /**
     * Proportional share of an amount, rounded half to even ("banker's
     * rounding") as required by the PSP's settlement files.
     */
    public static function share(int $amount, int $part, int $whole): int
    {
        if ($whole === 0) {
            return 0;
        }
        $numerator = $amount * $part;
        $quotient = intdiv($numerator, $whole);
        $remainder = $numerator % $whole;
        if (2 * $remainder > $whole || (2 * $remainder === $whole && $quotient % 2 === 1)) {
            $quotient++;
        }
        return $quotient;
    }
}
