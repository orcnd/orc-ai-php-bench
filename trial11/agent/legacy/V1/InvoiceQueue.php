<?php
declare(strict_types=1);
namespace Legacy\V1;

use App\Storage\FileStore;

/** Release 4.x (V1) producer and worker for invoice.requested messages. Frozen. */
final class InvoiceQueue
{
    /** @return array{type: string, id: string, customer: int, fee: float} */
    public static function request(FileStore $store, int $customerId, string $messageId): array
    {
        $customer = CustomerRecord::load($store, $customerId);
        return ['type' => 'invoice.requested', 'id' => $messageId, 'customer' => $customerId, 'fee' => $customer['monthly_fee']];
    }

    /** @param array<string, mixed> $message */
    public static function handle(FileStore $store, array $message): void
    {
        if (($message['type'] ?? null) !== 'invoice.requested') {
            throw new \UnexpectedValueException('Unknown message type');
        }
        if (!is_float($message['fee'] ?? null) && !is_int($message['fee'] ?? null)) {
            throw new \UnexpectedValueException('Message without fee');
        }
        $store->put('invoice-' . $message['id'], [
            'customer' => (int) $message['customer'],
            'amount' => (int) round($message['fee'] * 100),
        ]);
    }
}
