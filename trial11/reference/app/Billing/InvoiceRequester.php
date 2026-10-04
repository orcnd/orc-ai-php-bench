<?php
declare(strict_types=1);
namespace App\Billing;

use App\Customers\CustomerRepository;
use App\Storage\FileStore;

/** V2 producer of invoice.requested messages (the queue is shared by V1 and V2 workers). */
final class InvoiceRequester
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    /** @return array<string, mixed> */
    public function request(int $customerId, string $messageId): array
    {
        $customer = (new CustomerRepository($this->store))->find($customerId);
        // Same type and legacy "fee" for V1 workers; extra fields for V2 workers.
        return ['type' => 'invoice.requested', 'id' => $messageId, 'customer' => $customerId,
                'fee' => $customer->feeCents / 100, 'fee_cents' => $customer->feeCents, 'currency' => $customer->currency];
    }
}
