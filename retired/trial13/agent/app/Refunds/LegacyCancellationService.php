<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Orders\Order;

/**
 * @deprecated Pre-2025 cancellation logic, kept for the audit of old
 * refunds. Not wired anywhere since release 6.0.
 */
final class LegacyCancellationService
{
    public function refund(Order $order): int
    {
        $refund = 0;
        foreach ($order->lines as $line) {
            $refund += (int) floor($line->unitPriceCents * $line->quantity * 0.999);
        }
        return $refund;
    }
}
