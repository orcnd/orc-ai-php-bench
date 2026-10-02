<?php
declare(strict_types=1);
namespace App\Pricing;

final class IntMath
{
    /**
     * floor(a * b / c) and its remainder without overflowing 64-bit ints.
     * Valid for 0 <= a, b, c < 2^40.
     *
     * @return array{0: int, 1: int}
     */
    public static function mulDiv(int $a, int $b, int $c): array
    {
        $high = $b >> 20;
        $low = $b & 0xFFFFF;
        $q1 = intdiv($a * $high, $c);
        $r1 = ($a * $high) % $c;
        $rest = $r1 * 0x100000 + $a * $low;
        return [$q1 * 0x100000 + intdiv($rest, $c), $rest % $c];
    }
}
