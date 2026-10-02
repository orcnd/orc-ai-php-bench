<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Domain\LineType;
use App\Domain\OrderSnapshot;
use App\Ledger\Ledger;
use App\Orders\OrderRepository;

final class CancellationService
{
    private OrderRepository $orders;
    private Ledger $ledger;

    public function __construct(OrderRepository $orders, Ledger $ledger)
    {
        $this->orders = $orders;
        $this->ledger = $ledger;
    }

    /**
     * @param array<int|string, string|int> $lineIds line ids, or line id => unit count
     * @return array{order_id: int, refund_cents: int, status: string}
     */
    public function cancel(int $orderId, array $lineIds, string $requestId): array
    {
        if (isset($this->orders->responses[$orderId][$requestId])) {
            return $this->orders->responses[$orderId][$requestId];
        }
        $snapshot = $this->orders->find($orderId);
        $cancelled = $this->orders->cancelledUnits[$orderId] ?? [];
        $before = $this->entitlement($snapshot, $cancelled);
        foreach ($lineIds as $key => $value) {
            $id = is_int($key) ? $value : $key;
            if (!is_string($id) || !isset($snapshot->lines[$id])) {
                throw new \InvalidArgumentException('Unknown line ' . $id);
            }
            $remaining = $snapshot->lines[$id]->quantity - ($cancelled[$id] ?? 0);
            $units = is_int($key) ? $remaining : $value;
            if (!is_int($key) && (!is_int($units) || $units < 1 || $units > $remaining)) {
                throw new \InvalidArgumentException('Invalid unit count for line ' . $id);
            }
            $cancelled[$id] = ($cancelled[$id] ?? 0) + (int) $units;
        }
        $amount = $this->entitlement($snapshot, $cancelled) - $before;
        $response = [
            'order_id' => $orderId,
            'refund_cents' => $amount,
            'status' => $amount > 0 ? 'refunded' : 'nothing_to_refund',
        ];
        if ($amount > 0) {
            // May throw during the month-end close; nothing is persisted before it succeeds.
            $this->ledger->record($orderId, $requestId, $amount);
        }
        $this->orders->cancelledUnits[$orderId] = $cancelled;
        $this->orders->responses[$orderId][$requestId] = $response;
        return $response;
    }

    /** @param array<string, int> $cancelled units cancelled per line */
    private function entitlement(OrderSnapshot $snapshot, array $cancelled): int
    {
        $total = 0;
        foreach ($snapshot->lines as $id => $line) {
            if ($line->type === LineType::CREDIT) {
                $total += $line->total();
            } elseif ($line->type === LineType::MERCH && isset($cancelled[$id])) {
                $net = $line->total() - ($snapshot->allocations[$id] ?? 0);
                $total += intdiv($net * $cancelled[$id], $line->quantity);
            }
        }
        return max(0, $total);
    }
}
