<?php
declare(strict_types=1);
namespace App\Pricing;

final class DiscountAllocator
{
    /**
     * Spread an amount over weighted keys.
     *
     * @param array<string, int> $weights
     * @return array<string, int>
     */
    public function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $shares = [];
        foreach ($weights as $key => $weight) {
            $shares[$key] = $total > 0 ? (int) round($amount * $weight / $total) : 0;
        }
        return $shares;
    }
}
