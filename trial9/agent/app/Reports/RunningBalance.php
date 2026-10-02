<?php
declare(strict_types=1);
namespace App\Reports;

final class RunningBalance
{
    /**
     * Account balance at the start of $day (Y-m-d): the balance after the
     * last transaction booked before that day. Transactions are given in
     * booking order.
     *
     * @param list<Transaction> $transactions
     */
    public function openingBalance(array $transactions, string $day, int $initialCents = 0): int
    {
        $balances = [];
        $balance = $initialCents;
        foreach ($transactions as $t) {
            $balance += $t->amountCents;
            $balances[] = [substr($t->bookedAt, 0, 10), $balance];
        }
        $latestDay = null;
        $opening = $initialCents;
        foreach ($balances as [$date, $after]) {
            if ($date < $day && $date !== $latestDay) {
                $latestDay = $date;
                $opening = $after;
            }
        }
        return $opening;
    }
}
