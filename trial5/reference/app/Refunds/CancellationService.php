<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Domain\LineType;
use App\Domain\OrderSnapshot;
use App\Ledger\Ledger;
use App\Notifications\Outbox;
use App\Orders\OrderRepository;
use App\Support\Clock;

final class CancellationService
{
    private const SHOP_TIMEZONE = 'Europe/Berlin';
    private const WITHDRAWAL_DAYS = 14;

    private OrderRepository $orders;
    private Ledger $ledger;
    private Outbox $outbox;
    private Clock $clock;

    public function __construct(OrderRepository $orders, Ledger $ledger, Outbox $outbox, Clock $clock)
    {
        $this->orders = $orders;
        $this->ledger = $ledger;
        $this->outbox = $outbox;
        $this->clock = $clock;
    }

    /**
     * @param array<int|string, string|int> $lineIds line ids, or line id => unit count
     * @return array{order_id: int, refund_cents: int, status: string}
     */
    public function cancel(int $orderId, array $lineIds, string $requestId): array
    {
        return $this->orders->transaction($orderId, function () use ($orderId, $lineIds, $requestId): array {
            $state = $this->orders->cancellationState($orderId);
            if (isset($state['responses'][$requestId])) {
                return $state['responses'][$requestId];
            }
            $snapshot = $this->orders->find($orderId);
            $before = $this->entitlement($snapshot, $state['units'], $state['shipping_refunded']);
            $wasComplete = $this->fullyCancelled($snapshot, $state['units']);
            $cancelled = $state['units'];
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
            $shippingRefunded = $state['shipping_refunded'];
            if (!$wasComplete && $this->fullyCancelled($snapshot, $cancelled) && $this->withinWithdrawal($snapshot)) {
                $shippingRefunded = true;
            }
            $amount = $this->entitlement($snapshot, $cancelled, $shippingRefunded) - $before;
            $response = [
                'order_id' => $orderId,
                'refund_cents' => $amount,
                'status' => $amount > 0 ? 'refunded' : 'nothing_to_refund',
            ];
            if ($amount > 0) {
                // Throws during the month-end close; nothing is persisted before it succeeds.
                $this->ledger->record($orderId, $requestId, $amount);
                $this->outbox->publish('refund.issued', [
                    'order_id' => $orderId,
                    'request_id' => $requestId,
                    'refund_cents' => $amount,
                ]);
            }
            $state['units'] = $cancelled;
            $state['shipping_refunded'] = $shippingRefunded;
            $state['responses'][$requestId] = $response;
            $this->orders->saveCancellationState($orderId, $state);
            return $response;
        });
    }

    private function withinWithdrawal(OrderSnapshot $snapshot): bool
    {
        $zone = new \DateTimeZone(self::SHOP_TIMEZONE);
        $placed = (new \DateTimeImmutable($snapshot->placedAt))->setTimezone($zone);
        $deadline = $placed->setTime(0, 0)->modify('+' . (self::WITHDRAWAL_DAYS + 1) . ' days');
        return $this->clock->now() < $deadline;
    }

    /** @param array<string, int> $cancelled */
    private function fullyCancelled(OrderSnapshot $snapshot, array $cancelled): bool
    {
        foreach ($snapshot->lines as $id => $line) {
            if ($line->type === LineType::MERCH && ($cancelled[$id] ?? 0) < $line->quantity) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, int> $cancelled units cancelled per line */
    private function entitlement(OrderSnapshot $snapshot, array $cancelled, bool $shippingRefunded): int
    {
        $total = 0;
        foreach ($snapshot->lines as $id => $line) {
            if ($line->type === LineType::CREDIT) {
                $total += $line->total();
            } elseif ($line->type === LineType::SHIPPING && $shippingRefunded) {
                $total += $line->total();
            } elseif ($line->type === LineType::MERCH && isset($cancelled[$id])) {
                $net = $line->total() - ($snapshot->allocations[$id] ?? 0);
                $total += intdiv($net * $cancelled[$id], $line->quantity);
            }
        }
        return max(0, $total);
    }
}
