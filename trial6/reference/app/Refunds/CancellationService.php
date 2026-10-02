<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Domain\LineType;
use App\Domain\OrderSnapshot;
use App\Ledger\Ledger;
use App\Notifications\Outbox;
use App\Notifications\OutboxUnavailableException;
use App\Orders\OrderRepository;
use App\Support\Clock;

/**
 * @phpstan-type State array{units: array<string, int>, responses: array<string, array{order_id: int, refund_cents: int, status: string}>, shipping_refunded: bool, pending: array{request_id: string, units: array<string, int>, shipping_refunded: bool, response: array{order_id: int, refund_cents: int, status: string}}|null, unsent: list<string>}
 */
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
            $state = $this->recover($orderId, $this->orders->cancellationState($orderId));
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
            if ($amount <= 0) {
                $state['units'] = $cancelled;
                $state['shipping_refunded'] = $shippingRefunded;
                $state['responses'][$requestId] = $response;
                $this->orders->saveCancellationState($orderId, $state);
                return $response;
            }
            // Intent first: a crash after the ledger booking is recovered from the ledger.
            $state['pending'] = [
                'request_id' => $requestId,
                'units' => $cancelled,
                'shipping_refunded' => $shippingRefunded,
                'response' => $response,
            ];
            $this->orders->saveCancellationState($orderId, $state);
            try {
                $this->ledger->record($orderId, $requestId, $amount);
            } catch (\Throwable $error) {
                $state['pending'] = null;
                $this->orders->saveCancellationState($orderId, $state);
                throw $error;
            }
            $state = $this->commitPending($state);
            $this->orders->saveCancellationState($orderId, $state);
            $this->flush($orderId, $state);
            return $response;
        });
    }

    /**
     * Finish or discard work interrupted by a crash, and deliver e-mails
     * that could not be published earlier.
     *
     * @param State $state
     * @return State
     */
    private function recover(int $orderId, array $state): array
    {
        if ($state['pending'] !== null) {
            $booked = false;
            foreach ($this->ledger->entries() as $entry) {
                if ($entry['order_id'] === $orderId && $entry['request_id'] === $state['pending']['request_id']) {
                    $booked = true;
                }
            }
            $state = $booked ? $this->commitPending($state) : ['pending' => null] + $state;
            $this->orders->saveCancellationState($orderId, $state);
        }
        return $this->flush($orderId, $state);
    }

    /**
     * @param State $state
     * @return State
     */
    private function commitPending(array $state): array
    {
        $pending = $state['pending'];
        if ($pending === null) {
            return $state;
        }
        $state['units'] = $pending['units'];
        $state['shipping_refunded'] = $pending['shipping_refunded'];
        $state['responses'][$pending['request_id']] = $pending['response'];
        $state['unsent'][] = $pending['request_id'];
        $state['pending'] = null;
        return $state;
    }

    /**
     * Publish each unsent event exactly once; the outbox itself is checked so a crash cannot duplicate.
     *
     * @param State $state
     * @return State
     */
    private function flush(int $orderId, array $state): array
    {
        if ($state['unsent'] === []) {
            return $state;
        }
        $published = [];
        foreach ($this->outbox->events() as $event) {
            if ($event['type'] === 'refund.issued' && $event['payload']['order_id'] === $orderId) {
                $published[(string) $event['payload']['request_id']] = true;
            }
        }
        foreach ($state['unsent'] as $index => $requestId) {
            if (!isset($published[$requestId])) {
                try {
                    $this->outbox->publish('refund.issued', [
                        'order_id' => $orderId,
                        'request_id' => $requestId,
                        'refund_cents' => $state['responses'][$requestId]['refund_cents'],
                    ]);
                } catch (OutboxUnavailableException $maintenance) {
                    break;
                }
            }
            unset($state['unsent'][$index]);
        }
        $state['unsent'] = array_values($state['unsent']);
        $this->orders->saveCancellationState($orderId, $state);
        return $state;
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
