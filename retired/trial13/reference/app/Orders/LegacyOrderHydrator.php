<?php
declare(strict_types=1);
namespace App\Orders;

/**
 * Builds orders from rows migrated from the old wholesale ERP (2024). The
 * ERP stored line totals and quantities but no unit prices: wholesale
 * prices are negotiated per line ("3 pallets for 1000.00").
 */
final class LegacyOrderHydrator
{
    /** @param array{order_no: string, freight: int, positions: list<array{article: string, qty: int, total: int, rebate?: int}>} $row */
    public function hydrate(array $row): Order
    {
        $lines = [];
        foreach ($row['positions'] as $position) {
            $unit = intdiv($position['total'], $position['qty']);
            $lines[] = new OrderLine($position['article'], $position['qty'], $unit, $position['total'], $position['rebate'] ?? 0);
        }
        return new Order($row['order_no'], 'wholesale', $lines, $row['freight']);
    }
}
