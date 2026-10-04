<?php
declare(strict_types=1);
namespace App\Reports;

use App\Orders\Order;

final class RevenueReport
{
    /**
     * @param list<Order> $orders
     * @return array<string, int> merchandise revenue per channel
     */
    public function byChannel(array $orders): array
    {
        $out = [];
        foreach ($orders as $order) {
            $out[$order->channel] = ($out[$order->channel] ?? 0) + $order->merchandiseCents();
        }
        ksort($out);
        return $out;
    }
}
