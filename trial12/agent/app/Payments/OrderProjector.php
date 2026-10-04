<?php
declare(strict_types=1);
namespace App\Payments;

/**
 * Builds the payment state of orders from the events on the payments
 * topic and decides which commands to send to the payment provider (PSP).
 * See docs/EVENTS.md.
 */
final class OrderProjector
{
    /** @var array<string, array{status: string, total: int, captured: int, refunded: int}> */
    private array $orders = [];
    /** @var list<array{type: string, ref: string, amount: int}> */
    private array $commands = [];

    /** @param array<string, mixed> $event */
    public function apply(array $event): void
    {
        $order = (string) $event['order'];
        switch ($event['type']) {
            case 'OrderCreated':
                $this->orders[$order] = ['status' => 'pending', 'total' => (int) $event['total'], 'captured' => 0, 'refunded' => 0];
                break;
            case 'PaymentAuthorized':
                if ($this->orders[$order]['status'] === 'pending') {
                    $this->orders[$order]['status'] = 'authorized';
                }
                break;
            case 'PaymentAuthorizationTimedOut':
                $this->orders[$order]['status'] = 'failed';
                break;
            case 'PaymentCaptured':
                $this->orders[$order]['captured'] += (int) $event['amount'];
                $this->orders[$order]['status'] = 'captured';
                break;
            case 'RefundRequested':
                if ($this->orders[$order]['captured'] > 0) {
                    $this->commands[] = ['type' => 'IssueRefund', 'ref' => (string) $event['refund'], 'amount' => (int) $event['amount']];
                }
                break;
            case 'RefundCompleted':
                $this->orders[$order]['refunded'] += (int) $event['amount'];
                $this->orders[$order]['status'] = $this->orders[$order]['refunded'] >= $this->orders[$order]['captured'] ? 'refunded' : 'partially_refunded';
                break;
            case 'OrderCancelled':
                $this->orders[$order]['status'] = 'cancelled';
                break;
        }
    }

    /** @return array{status: string, total: int, captured: int, refunded: int} */
    public function state(string $order): array
    {
        return $this->orders[$order];
    }

    /** @return list<array{type: string, ref: string, amount: int}> commands sent to the PSP so far */
    public function commands(): array
    {
        return $this->commands;
    }
}
