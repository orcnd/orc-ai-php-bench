<?php
declare(strict_types=1);
namespace App\Billing;

use App\Storage\FileStore;

/** Read side for invoices created by V1 or V2 workers. */
final class Invoices
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    /** @return array{customer: int, amount: int, currency: string}|null */
    public function get(string $messageId): ?array
    {
        $data = $this->store->get('invoice-' . $messageId);
        if (!is_array($data)) {
            return null;
        }
        return ['customer' => (int) $data['customer'], 'amount' => (int) $data['amount'], 'currency' => (string) ($data['currency'] ?? 'EUR')];
    }
}
