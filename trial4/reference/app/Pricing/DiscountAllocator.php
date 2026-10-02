<?php
declare(strict_types=1);
namespace App\Pricing;

final class DiscountAllocator
{
    /**
     * Largest-remainder allocation; ties resolved in input order.
     *
     * @param array<string, int> $weights
     * @return array<string, int>
     */
    public function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $shares = [];
        $remainders = [];
        $position = 0;
        foreach ($weights as $key => $weight) {
            [$share, $remainder] = $total > 0 ? IntMath::mulDiv($amount, $weight, $total) : [0, 0];
            $shares[$key] = $share;
            $remainders[] = [$remainder, $position++, $key];
        }
        $left = $total > 0 ? $amount - array_sum($shares) : 0;
        usort($remainders, function (array $a, array $b): int {
            return [$b[0], $a[1]] <=> [$a[0], $b[1]];
        });
        for ($i = 0; $i < $left; $i++) {
            $shares[$remainders[$i][2]]++;
        }
        return $shares;
    }
}
