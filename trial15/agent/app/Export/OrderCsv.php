<?php
declare(strict_types=1);
namespace App\Export;

/** Order export for the accounting team (opened in Excel). */
final class OrderCsv
{
    /** @param list<array{number: string, customer: string, note: string, total_cents: int}> $orders */
    public function render(array $orders): string
    {
        $lines = ['number,customer,note,total'];
        foreach ($orders as $order) {
            $lines[] = implode(',', [
                $order['number'],
                '"' . addslashes($order['customer']) . '"',
                '"' . addslashes($order['note']) . '"',
                number_format($order['total_cents'] / 100, 2, '.', ''),
            ]);
        }
        return implode("\r\n", $lines) . "\r\n";
    }
}
