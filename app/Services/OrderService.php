<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

final class OrderService
{
    private ShippingService $shipping;

    public function __construct(ShippingService $shipping)
    {
        $this->shipping = $shipping;
    }

    /** @return array{subtotal_cents: int, discount_cents: int, shipping_cents: int, total_cents: int} */
    public function checkout(Order $order): array
    {
        $subtotal = 0;
        foreach ($order->items() as $item) {
            $subtotal += $item['cents'];
        }

        $discount = 0;
        if ($order->couponPercent() !== null) {
            $discount = (int) round($subtotal * $order->couponPercent() / 100);
            $discount = max(0, min($subtotal, $discount));
        }

        $shipping = $this->shipping->fee($order->customer());

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'shipping_cents' => $shipping,
            'total_cents' => $subtotal - $discount + $shipping,
        ];
    }
}
