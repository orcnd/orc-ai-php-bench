<?php
declare(strict_types=1);
namespace App\Reports;

final class RangeReport
{
    /**
     * Transactions booked from $from to $to, both days inclusive (Y-m-d).
     *
     * @param list<Transaction> $transactions
     * @return list<Transaction>
     */
    public function between(array $transactions, string $from, string $to): array
    {
        return array_values(array_filter($transactions, function (Transaction $t) use ($from, $to): bool {
            return substr($t->bookedAt, 0, 10) >= $from && substr($t->bookedAt, 0, 10) <= $to;
        }));
    }

    /** @param list<Transaction> $transactions */
    public function sumBetween(array $transactions, string $from, string $to): int
    {
        return array_sum(array_map(function (Transaction $t): int {
            return $t->amountCents;
        }, $this->between($transactions, $from, $to)));
    }
}
