<?php
declare(strict_types=1);
namespace App\Money;

/** Money splitting used by billing, refunds and payouts. See docs/ALLOCATION.md. */
final class Allocation
{
    /**
     * Split $total cents across keys in proportion to their weights.
     *
     * @param array<string, int> $weights non-negative
     * @return array<string, int>
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $shares = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $shares[$key] = (int) floor($total * $weight / $sum);
            $allocated += $shares[$key];
        }
        $keys = array_keys($weights);
        for ($i = 0; $allocated < $total; $i++) {
            $shares[$keys[$i % count($keys)]]++;
            $allocated++;
        }
        return $shares;
    }

    /**
     * Part of a subscription $amount for the days [$from, $to) of the billing
     * period [$periodStart, $periodEnd). Dates are Y-m-d.
     */
    public static function prorate(int $amount, string $from, string $to, string $periodStart, string $periodEnd): int
    {
        $days = self::days($from, $to);
        $total = self::days($periodStart, $periodEnd);
        return (int) round($amount * $days / $total);
    }

    /**
     * Split $total into $count instalments.
     *
     * @return list<int>
     */
    public static function installments(int $total, int $count): array
    {
        $each = (int) round($total / $count);
        $parts = array_fill(0, $count, $each);
        $parts[$count - 1] = $total - $each * ($count - 1);
        return $parts;
    }

    private static function days(string $from, string $to): int
    {
        return (int) ((strtotime($to) - strtotime($from)) / 86400);
    }
}
