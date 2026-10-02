<?php
declare(strict_types=1);
namespace App\Ledger;

use App\Storage\FileStore;

/**
 * Finance ledger. Finance closes it during the month-end close; writes are
 * rejected until it is reopened.
 */
final class Ledger
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    public function record(int $orderId, string $requestId, int $amount): void
    {
        $this->store->transaction('ledger', function () use ($orderId, $requestId, $amount): void {
            if ($this->store->get('ledger-closed', false) === true) {
                throw new LedgerClosedException('Ledger is closed for month-end');
            }
            $entries = $this->entries();
            $entries[] = ['order_id' => $orderId, 'request_id' => $requestId, 'amount' => $amount];
            $this->store->put('ledger', $entries);
        });
    }

    public function close(): void
    {
        $this->store->put('ledger-closed', true);
    }

    public function reopen(): void
    {
        $this->store->put('ledger-closed', false);
    }

    /** @return list<array{order_id: int, request_id: string, amount: int}> */
    public function entries(): array
    {
        /** @var list<array{order_id: int, request_id: string, amount: int}> $entries */
        $entries = $this->store->get('ledger', []);
        return $entries;
    }
}
