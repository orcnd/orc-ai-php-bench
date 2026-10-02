<?php
declare(strict_types=1);
namespace App;
final class RefundService
{
    private OrderRepository $repository;
    private PricingPipeline $pricing;
    public function __construct(OrderRepository $repository, PricingPipeline $pricing)
    {
        $this->repository = $repository;
        $this->pricing = $pricing;
    }
    /** @return array{order_id: int, refund_cents: int} */
    public function cancel(int $id): array
    {
        if (isset($this->repository->refunds[$id])) { return $this->repository->refunds[$id]; }
        if (!isset($this->repository->snapshots[$id])) {
            $order = $this->repository->orders[$id];
            $this->repository->snapshots[$id] = $this->pricing->price($order['lines'], $order['promotion']);
        }
        $snapshot = $this->repository->snapshots[$id];
        $amount = $snapshot['merchandise'] + $snapshot['credit'] - $snapshot['discount'];
        if (!is_int($amount)) { throw new \OverflowException('Refund outside integer range'); }
        $amount = max(0, $amount);
        $response = ['order_id' => $id, 'refund_cents' => $amount];
        $this->repository->ledger[] = ['order_id' => $id, 'amount' => $amount];
        $this->repository->refunds[$id] = $response;
        return $response;
    }
}
