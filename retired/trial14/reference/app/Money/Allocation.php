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
        if ($weights === []) {
            return [];
        }
        $sum = array_sum($weights);
        if ($sum === 0) {
            $weights = array_fill_keys(array_keys($weights), 1);
            $sum = count($weights);
        }
        $magnitude = abs($total);
        $shares = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            [$share, $remainder] = self::mulDiv($magnitude, $weight, $sum);
            $shares[(string) $key] = $share;
            $remainders[] = [$remainder, (string) $key];
        }
        $left = $magnitude - array_sum($shares);
        usort($remainders, function (array $a, array $b): int {
            return $a[0] !== $b[0] ? $b[0] <=> $a[0] : strcmp($a[1], $b[1]);
        });
        for ($i = 0; $i < $left; $i++) {
            $shares[$remainders[$i][1]]++;
        }
        if ($total < 0) {
            foreach ($shares as $key => $share) {
                $shares[$key] = -$share;
            }
        }
        return $shares;
    }

    /**
     * Part of a subscription $amount for the days [$from, $to) of the billing
     * period [$periodStart, $periodEnd). Dates are Y-m-d.
     */
    public static function prorate(int $amount, string $from, string $to, string $periodStart, string $periodEnd): int
    {
        // Cumulative rounding makes consecutive ranges add up exactly.
        return self::cumulative($amount, $periodStart, $to, $periodEnd) - self::cumulative($amount, $periodStart, $from, $periodEnd);
    }

    /**
     * Split $total into $count instalments.
     *
     * @return list<int>
     */
    public static function installments(int $total, int $count): array
    {
        $base = intdiv($total, $count);
        $extra = $total - $base * $count;
        $parts = [];
        for ($i = 0; $i < $count; $i++) {
            $parts[] = $base + ($i < $extra ? 1 : 0);
        }
        return $parts;
    }

    private static function cumulative(int $amount, string $periodStart, string $day, string $periodEnd): int
    {
        $total = self::days($periodStart, $periodEnd);
        return intdiv(2 * $amount * self::days($periodStart, $day) + $total, 2 * $total);
    }

    private static function days(string $from, string $to): int
    {
        $utc = new \DateTimeZone('UTC');
        return (int) (new \DateTimeImmutable($from, $utc))->diff(new \DateTimeImmutable($to, $utc))->format('%r%a');
    }

    /**
     * floor(a * b / c) and remainder without 64-bit overflow, for 0 <= a, b, c < 2^40.
     *
     * @return array{0: int, 1: int}
     */
    private static function mulDiv(int $a, int $b, int $c): array
    {
        $high = $b >> 20;
        $low = $b & 0xFFFFF;
        $q1 = intdiv($a * $high, $c);
        $r1 = ($a * $high) % $c;
        $rest = $r1 * 0x100000 + $a * $low;
        return [$q1 * 0x100000 + intdiv($rest, $c), $rest % $c];
    }
}
