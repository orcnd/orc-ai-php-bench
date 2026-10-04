<?php
declare(strict_types=1);
namespace App\Invoices;

use App\Orders\Order;

/** Reprints invoices on request (customer portal "download invoice"). */
final class InvoiceReprinter
{
    /** @return array{number: string, lines: list<array{sku: string, qty: int, amount: int}>, total: int} */
    public function render(Order $order): array
    {
        $lines = [];
        foreach ($order->lines as $line) {
            $lines[] = ['sku' => $line->sku, 'qty' => $line->quantity, 'amount' => $line->lineTotalCents - $line->discountCents];
        }
        return ['number' => $order->number, 'lines' => $lines, 'total' => $order->merchandiseCents() + $order->shippingCents];
    }
}
