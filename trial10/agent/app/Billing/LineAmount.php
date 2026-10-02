<?php
declare(strict_types=1);
namespace App\Billing;

final class LineAmount
{
    /**
     * Net amount of a product line. Unit prices are stored in tenths of a
     * cent ("mills", 12.974 EUR = 12974). The line is rounded once.
     */
    public function net(int $unitPriceMills, int $quantity): int
    {
        return intdiv($unitPriceMills * $quantity + 5, 10);
    }
}
