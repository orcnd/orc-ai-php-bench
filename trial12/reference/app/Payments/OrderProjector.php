<?php
declare(strict_types=1);
namespace App\Payments;

/**
 * Builds the payment state of orders from the events on the payments
 * topic and decides which commands to send to the payment provider (PSP).
 * See docs/EVENTS.md.
 *
 * Order-independent: events are deduplicated by id and folded into
 * commutative facts (sets and sums). Commands are derived incrementally from
 * those facts and each one is sent at most once, so the result never depends
 * on arrival order and each event costs O(1) amortised.
 */
final class OrderProjector
{
    /** @var array<string, true> */
    private array $seen = [];
    /** @var array<string, array{total: int, cancelled: bool, captured: int, refunded: int, auths: array<string, int>, capturedAuths: array<string, true>, refunds: array<string, int>}> */
    private array $orders = [];
    /** @var list<array{type: string, ref: string, amount: int}> */
    private array $commands = [];
    /** @var array<string, true> */
    private array $sent = [];

    /** @param array<string, int|string> $event */
    public function apply(array $event): void
    {
        $id = (string) $event['id'];
        if (isset($this->seen[$id])) {
            return;
        }
        $this->seen[$id] = true;
        $order = (string) $event['order'];
        $f = $this->orders[$order] ?? ['total' => 0, 'cancelled' => false, 'captured' => 0, 'refunded' => 0,
            'auths' => [], 'capturedAuths' => [], 'refunds' => []];
        $hadCapture = $f['captured'] > 0;
        switch ($event['type']) {
            case 'OrderCreated':
                $f['total'] = (int) $event['total'];
                break;
            case 'OrderCancelled':
                $f['cancelled'] = true;
                $this->orders[$order] = $f;
                foreach ($f['auths'] as $auth => $amount) {
                    $this->void($order, $auth, $amount);
                }
                return;
            case 'PaymentAuthorized':
                $auth = (string) $event['auth'];
                $f['auths'][$auth] = (int) $event['amount'];
                $this->orders[$order] = $f;
                if (($f['cancelled'] || $f['captured'] > 0) && !isset($f['capturedAuths'][$auth])) {
                    $this->void($order, $auth, (int) $event['amount']);
                }
                return;
            case 'PaymentCaptured':
                $f['captured'] += (int) $event['amount'];
                $f['capturedAuths'][(string) $event['auth']] = true;
                break;
            case 'RefundRequested':
                $refund = (string) $event['refund'];
                $f['refunds'][$refund] = (int) $event['amount'];
                $this->orders[$order] = $f;
                if ($f['captured'] > 0) {
                    $this->issue($order, $refund, (int) $event['amount']);
                }
                return;
            case 'RefundCompleted':
                $f['refunded'] += (int) $event['amount'];
                break;
        }
        $this->orders[$order] = $f;
        if (!$hadCapture && $f['captured'] > 0) {
            // First capture: release held refunds and void the authorisations that will never be captured.
            ksort($f['refunds']);
            foreach ($f['refunds'] as $refund => $amount) {
                $this->issue($order, (string) $refund, $amount);
            }
            foreach ($f['auths'] as $auth => $amount) {
                if (!isset($f['capturedAuths'][$auth])) {
                    $this->void($order, (string) $auth, $amount);
                }
            }
        }
    }

    /** @return array{status: string, total: int, captured: int, refunded: int} */
    public function state(string $order): array
    {
        $f = $this->orders[$order] ?? ['total' => 0, 'cancelled' => false, 'captured' => 0, 'refunded' => 0,
            'auths' => [], 'capturedAuths' => [], 'refunds' => []];
        if ($f['cancelled']) {
            $status = 'cancelled';
        } elseif ($f['captured'] > 0) {
            $status = $f['refunded'] >= $f['captured'] ? 'refunded' : ($f['refunded'] > 0 ? 'partially_refunded' : 'captured');
        } else {
            $status = $f['auths'] !== [] ? 'authorized' : 'pending';
        }
        return ['status' => $status, 'total' => $f['total'], 'captured' => $f['captured'], 'refunded' => $f['refunded']];
    }

    /** @return list<array{type: string, ref: string, amount: int}> commands sent to the PSP so far */
    public function commands(): array
    {
        return $this->commands;
    }

    private function issue(string $order, string $refund, int $amount): void
    {
        $this->send($order . ':refund:' . $refund, ['type' => 'IssueRefund', 'ref' => $refund, 'amount' => $amount]);
    }

    private function void(string $order, string $auth, int $amount): void
    {
        $this->send($order . ':void:' . $auth, ['type' => 'VoidAuthorization', 'ref' => $auth, 'amount' => $amount]);
    }

    /** @param array{type: string, ref: string, amount: int} $command */
    private function send(string $key, array $command): void
    {
        if (!isset($this->sent[$key])) {
            $this->sent[$key] = true;
            $this->commands[] = $command;
        }
    }
}
