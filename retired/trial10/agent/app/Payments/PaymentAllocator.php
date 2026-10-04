<?php
declare(strict_types=1);
namespace App\Payments;

final class PaymentAllocator
{
    /**
     * Apply an unassigned payment to a customer's open invoices.
     *
     * @param array<string, int> $openAmounts invoice number => open amount, oldest due date first
     * @return array<string, int> invoice number => amount applied
     */
    public function allocate(int $payment, array $openAmounts): array
    {
        $applied = [];
        foreach ($openAmounts as $number => $open) {
            $amount = min($open, $payment);
            $applied[$number] = $amount;
            $payment -= $amount;
        }
        return $applied;
    }
}
