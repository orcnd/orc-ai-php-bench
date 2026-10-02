<?php
declare(strict_types=1);
namespace App\Orders;

use App\Domain\OrderSnapshot;

final class OrderRepository
{
    /** @var array<int, OrderSnapshot> */
    private array $snapshots = [];
    /** @var array<int, array<string, int>> units cancelled per order and line */
    public array $cancelledUnits = [];
    /** @var array<int, array<string, array{order_id: int, refund_cents: int, status: string}>> */
    public array $responses = [];

    public function save(OrderSnapshot $snapshot): void
    {
        if (isset($this->snapshots[$snapshot->orderId])) {
            throw new \LogicException('Order ' . $snapshot->orderId . ' already placed');
        }
        $this->snapshots[$snapshot->orderId] = $snapshot;
    }

    public function find(int $orderId): OrderSnapshot
    {
        if (!isset($this->snapshots[$orderId])) {
            throw new \OutOfBoundsException('No order ' . $orderId);
        }
        return $this->snapshots[$orderId];
    }
}
