<?php
declare(strict_types=1);
namespace App\Ledger;

final class Ledger
{
    /** @var list<array{order_id: int, request_id: string, amount: int}> */
    private array $entries = [];

    public function record(int $orderId, string $requestId, int $amount): void
    {
        $this->entries[] = ['order_id' => $orderId, 'request_id' => $requestId, 'amount' => $amount];
    }

    /** @return list<array{order_id: int, request_id: string, amount: int}> */
    public function entries(): array
    {
        return $this->entries;
    }
}
