<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Orders\OrderRepository;

final class CancellationService
{
    private OrderRepository $orders;
    private CancellationCalculator $calculator;

    public function __construct(OrderRepository $orders, ?CancellationCalculator $calculator = null)
    {
        $this->orders = $orders;
        $this->calculator = $calculator ?? new CancellationCalculator();
    }

    /**
     * @param array<string, int>|null $quantities
     * @return array{order: string, refund_cents: int}
     */
    public function cancel(string $orderNumber, ?array $quantities = null): array
    {
        $order = $this->orders->find($orderNumber);
        return ['order' => $order->number, 'refund_cents' => $this->calculator->refund($order, $quantities)];
    }
}
