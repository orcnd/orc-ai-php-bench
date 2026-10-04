<?php
declare(strict_types=1);
namespace App\Billing;

use App\Storage\FileStore;

/** V2 worker for invoice.requested messages. */
final class InvoiceWorker
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    /** @param array<string, mixed> $message */
    public function handle(array $message): void
    {
        $this->store->put('invoice-' . $message['id'], [
            'customer' => (int) $message['customer'],
            'amount' => (int) $message['fee_cents'],
            'currency' => (string) $message['currency'],
        ]);
    }
}
