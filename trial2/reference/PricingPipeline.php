<?php
declare(strict_types=1);
namespace App;
final class PricingPipeline
{
    /** @param list<int> $lines @return array{merchandise: int, credit: int, discount: int} */
    public function price(array $lines, ?int $promotion): array
    {
        $merchandise = 0;
        $credit = 0;
        foreach ($lines as $line) {
            if ($line > 0) { $merchandise += $line; }
            else { $credit += $line; }
        }
        $discount = intdiv($merchandise * max(0, min(100, $promotion ?? 0)) + 50, 100);
        return ['merchandise' => $merchandise, 'credit' => $credit, 'discount' => $discount];
    }
}
