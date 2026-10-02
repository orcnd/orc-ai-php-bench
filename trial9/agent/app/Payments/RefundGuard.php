<?php
declare(strict_types=1);
namespace App\Payments;

/** Validates refund requests coming from the payment gateway webhook (decimal strings). */
final class RefundGuard
{
    /** @param list<string> $previousRefunds */
    public function canRefund(string $paid, array $previousRefunds, string $requested): bool
    {
        $refunded = 0.0;
        foreach ($previousRefunds as $refund) {
            $refunded += (float) $refund;
        }
        return $refunded + (float) $requested <= (float) $paid;
    }
}
