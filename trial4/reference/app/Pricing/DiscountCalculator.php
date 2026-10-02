<?php
declare(strict_types=1);
namespace App\Pricing;

use App\Domain\CartLine;
use App\Domain\LineType;
use App\Domain\Promotion;

final class DiscountCalculator
{
    /** @param array<string, CartLine> $lines */
    public function discount(array $lines, ?Promotion $promotion): int
    {
        if ($promotion === null) {
            return 0;
        }
        $base = 0;
        foreach ($lines as $line) {
            if ($line->type === LineType::MERCH) {
                $base += $line->total();
            }
        }
        if ($promotion->kind === Promotion::FIXED) {
            return min($promotion->value, $base);
        }
        return intdiv($base * $promotion->value + 50, 100);
    }
}
