<?php
declare(strict_types=1);
namespace App\Api;

use App\Reports\Transaction;

/** GET /api/transactions?from=Y-m-d&to=Y-m-d (both days inclusive). */
final class TransactionsEndpoint
{
    /**
     * @param list<Transaction> $transactions
     * @param array{from?: string, to?: string} $query
     * @return list<array{id: int, booked_at: string, amount: int}>
     */
    public function index(array $transactions, array $query): array
    {
        $rows = [];
        foreach ($transactions as $t) {
            if (isset($query['from']) && $t->bookedAt < $query['from']) {
                continue;
            }
            if (isset($query['to']) && $t->bookedAt > $query['to']) {
                continue;
            }
            $rows[] = ['id' => $t->id, 'booked_at' => $t->bookedAt, 'amount' => $t->amountCents];
        }
        return $rows;
    }
}
