<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Orders\Order;

final class CancellationCalculator
{
    /**
     * Refund for cancelling the given quantities per SKU (null = everything).
     * Shipping is refunded only when the whole order is cancelled.
     *
     * @param array<string, int>|null $quantities
     */
    public function refund(Order $order, ?array $quantities = null): int
    {
        $refund = 0;
        $everything = true;
        foreach ($order->lines as $line) {
            $qty = $quantities === null ? $line->quantity : min($line->quantity, $quantities[$line->sku] ?? 0);
            if ($qty < $line->quantity) {
                $everything = false;
            }
            if ($qty === 0) {
                continue;
            }
            $net = $line->lineTotalCents - $line->discountCents;
            // TODO(FIN-2911): legacy wholesale refunds are sometimes a cent short?
            $refund += $qty === $line->quantity ? $net : RefundRounding::share($net, $qty, $line->quantity);
        }
        return $everything ? $refund + $order->shippingCents : $refund;
    }
}
