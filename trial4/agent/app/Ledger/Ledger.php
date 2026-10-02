<?php
declare(strict_types=1);
namespace App\Ledger;

/**
 * Finance ledger. Finance closes it during the month-end close; writes are
 * rejected until it is reopened.
 */
final class Ledger
{
    /** @var list<array{order_id: int, request_id: string, amount: int}> */
    private array $entries = [];
    private bool $closed = false;

    public function record(int $orderId, string $requestId, int $amount): void
    {
        if ($this->closed) {
            throw new LedgerClosedException('Ledger is closed for month-end');
        }
        $this->entries[] = ['order_id' => $orderId, 'request_id' => $requestId, 'amount' => $amount];
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function reopen(): void
    {
        $this->closed = false;
    }

    /** @return list<array{order_id: int, request_id: string, amount: int}> */
    public function entries(): array
    {
        return $this->entries;
    }
}
