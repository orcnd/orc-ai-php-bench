<?php
declare(strict_types=1);
namespace App\Orders;

/** Builds orders from rows of the current shop database. */
final class OrderHydrator
{
    /** @param array{number: string, channel: string, shipping: int, lines: list<array{sku: string, qty: int, unit: int, discount?: int}>} $row */
    public function hydrate(array $row): Order
    {
        $lines = [];
        foreach ($row['lines'] as $line) {
            $lines[] = new OrderLine($line['sku'], $line['qty'], $line['unit'], $line['unit'] * $line['qty'], $line['discount'] ?? 0);
        }
        return new Order($row['number'], $row['channel'], $lines, $row['shipping']);
    }
}
