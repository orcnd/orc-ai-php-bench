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
     * @param list<string> $lineIds
     * @return array{order_id: int, refund_cents: int, status: string}
     */
    public function cancel(int $orderId, array $lineIds, string $requestId): array
    {
        if (isset($this->orders->responses[$orderId][$requestId])) {
            return $this->orders->responses[$orderId][$requestId];
        }
        $snapshot = $this->orders->find($orderId);
        foreach ($lineIds as $id) {
            if (!isset($snapshot->lines[$id])) {
                throw new \InvalidArgumentException('Unknown line ' . $id);
            }
        }
        $cancelled = $this->orders->cancelledLines[$orderId] ?? [];
        $before = $this->entitlement($snapshot, $cancelled);
        foreach ($lineIds as $id) {
            $cancelled[$id] = true;
        }
        $amount = $this->entitlement($snapshot, $cancelled) - $before;
        $this->orders->cancelledLines[$orderId] = $cancelled;
        $response = [
            'order_id' => $orderId,
            'refund_cents' => $amount,
            'status' => $amount > 0 ? 'refunded' : 'nothing_to_refund',
        ];
        if ($amount > 0) {
            $this->ledger->record($orderId, $requestId, $amount);
        }
        $this->orders->responses[$orderId][$requestId] = $response;
        return $response;
    }

    /** @param array<string, true> $cancelled */
    private function entitlement(OrderSnapshot $snapshot, array $cancelled): int
    {
        $total = 0;
        foreach ($snapshot->lines as $id => $line) {
            if ($line->type === LineType::CREDIT) {
                $total += $line->total();
            } elseif ($line->type === LineType::MERCH && isset($cancelled[$id])) {
                $total += $line->total() - ($snapshot->allocations[$id] ?? 0);
            }
        }
        return max(0, $total);
    }
}
