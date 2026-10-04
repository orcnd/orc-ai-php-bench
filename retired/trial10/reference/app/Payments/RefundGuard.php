<?php
declare(strict_types=1);
namespace App\Payments;

use App\Support\Money;

/** Validates refund requests coming from the payment gateway webhook (decimal strings). */
final class RefundGuard
{
    /** @param list<string> $previousRefunds */
    public function canRefund(string $paid, array $previousRefunds, string $requested): bool
    {
        $refunded = 0;
        foreach ($previousRefunds as $refund) {
            $refunded += Money::fromDecimal($refund);
        }
        return $refunded + Money::fromDecimal($requested) <= Money::fromDecimal($paid);
    }
}
