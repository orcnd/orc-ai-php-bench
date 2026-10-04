<?php
declare(strict_types=1);
namespace App\Events;

/** Sends the cancellation confirmation e-mail. */
final class OrderCancelledListener
{
    /** @var list<string> */
    public array $sent = [];

    /** @param array{order: string, refund_cents: int} $result */
    public function onCancelled(array $result): void
    {
        $this->sent[] = sprintf('Order %s cancelled, refund %s EUR', $result['order'], number_format($result['refund_cents'] / 100, 2));
    }
}
