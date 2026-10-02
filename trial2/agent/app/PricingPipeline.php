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
        $discount = (int) round(($merchandise + $credit) * ($promotion ?? 0) / 100);
        return ['merchandise' => $merchandise, 'credit' => $credit, 'discount' => $discount];
    }
}
