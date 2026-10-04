<?php
declare(strict_types=1);
namespace App\Billing;

use App\Customers\CustomerRepository;
use App\Storage\FileStore;

/** V2 worker for invoice.requested messages from V1 or V2 producers; at-least-once safe. */
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
        if (($message['type'] ?? null) !== 'invoice.requested') {
            throw new \UnexpectedValueException('Unknown message type');
        }
        $key = 'invoice-' . $message['id'];
        if ($this->store->get($key) !== null) {
            return;
        }
        $customerId = (int) $message['customer'];
        $fee = $message['fee'] ?? 0;
        $cents = isset($message['fee_cents']) ? (int) $message['fee_cents'] : (int) round((is_numeric($fee) ? (float) $fee : 0.0) * 100);
        $currency = isset($message['currency']) ? (string) $message['currency']
            : (new CustomerRepository($this->store))->find($customerId)->currency;
        $this->store->put($key, ['customer' => $customerId, 'amount' => $cents, 'currency' => $currency]);
    }
}
