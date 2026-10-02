<?php
declare(strict_types=1);
namespace App\Orders;

use App\Domain\CartLine;
use App\Domain\OrderSnapshot;
use App\Domain\Promotion;
use App\Storage\FileStore;

final class OrderRepository
{
    private FileStore $store;
    /** @var array<int, array<string, true>> */
    public array $cancelledLines = [];
    /** @var array<string, array{order_id: int, refund_cents: int, status: string}> */
    public array $responses = [];

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    public function save(OrderSnapshot $snapshot): void
    {
        $this->store->transaction('order-' . $snapshot->orderId, function () use ($snapshot): void {
            if ($this->store->get('order-' . $snapshot->orderId) !== null) {
                throw new \LogicException('Order ' . $snapshot->orderId . ' already placed');
            }
            $this->store->put('order-' . $snapshot->orderId, self::encode($snapshot));
        });
    }

    public function find(int $orderId): OrderSnapshot
    {
        $data = $this->store->get('order-' . $orderId);
        if (!is_array($data)) {
            throw new \OutOfBoundsException('No order ' . $orderId);
        }
        return self::decode($data);
    }

    /** @return array<string, mixed> */
    private static function encode(OrderSnapshot $snapshot): array
    {
        $lines = [];
        foreach ($snapshot->lines as $line) {
            $lines[] = [$line->id, $line->type, $line->unitCents, $line->quantity];
        }
        $promotion = $snapshot->promotion;
        return [
            'order_id' => $snapshot->orderId,
            'lines' => $lines,
            'promotion' => $promotion === null ? null : [$promotion->code, $promotion->kind, $promotion->value],
            'discount' => $snapshot->discount,
            'allocations' => $snapshot->allocations,
            'placed_at' => $snapshot->placedAt,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function decode(array $data): OrderSnapshot
    {
        /** @var list<array{0: string, 1: string, 2: int, 3: int}> $rows */
        $rows = $data['lines'];
        $lines = [];
        foreach ($rows as $row) {
            $lines[$row[0]] = new CartLine($row[0], $row[1], $row[2], $row[3]);
        }
        /** @var array{0: string, 1: string, 2: int}|null $promotion */
        $promotion = $data['promotion'];
        /** @var array<string, int> $allocations */
        $allocations = $data['allocations'];
        return new OrderSnapshot(
            (int) $data['order_id'],
            $lines,
            $promotion === null ? null : new Promotion($promotion[0], $promotion[1], $promotion[2]),
            (int) $data['discount'],
            $allocations,
            (string) $data['placed_at']
        );
    }
}
