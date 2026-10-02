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
        $order = $this->repository->orders[$id];
        $snapshot = $this->pricing->price($order['lines'], $order['promotion']);
        $this->repository->snapshots[$id] = $snapshot;
        $amount = $snapshot['merchandise'] + $snapshot['credit'] - $snapshot['discount'] + $order['shipping'];
        $response = ['order_id' => $id, 'refund_cents' => $amount];
        $this->repository->ledger[] = ['order_id' => $id, 'amount' => $amount];
        return $response;
    }
}
