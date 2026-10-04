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
                self::field($order['number']),
                self::field(self::neutralise($order['customer'])),
                self::field(self::neutralise($order['note'])),
                number_format($order['total_cents'] / 100, 2, '.', ''),
            ]);
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    /** RFC 4180 quoting. */
    private static function field(string $value): string
    {
        if (strpbrk($value, ",\"\r\n") === false) {
            return $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /** Prevent Excel formula injection in free-text columns. */
    private static function neutralise(string $value): string
    {
        return $value !== '' && strpos("=+-@\t\r", $value[0]) !== false ? "'" . $value : $value;
    }
}
