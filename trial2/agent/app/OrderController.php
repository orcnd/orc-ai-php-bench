<?php
declare(strict_types=1);
namespace App;
final class OrderController
{
    private RefundService $refunds;
    public function __construct(RefundService $refunds) { $this->refunds = $refunds; }
    /** @return array{order_id: int, refund_cents: int} */
    public function cancel(int $id): array { return $this->refunds->cancel($id); }
}
