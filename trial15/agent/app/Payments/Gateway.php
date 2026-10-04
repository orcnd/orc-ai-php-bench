<?php
declare(strict_types=1);
namespace App\Payments;

use App\Exception\PaymentFailed;

interface Gateway
{
    /**
     * Charge a payment token; returns the provider transaction id.
     *
     * @throws PaymentFailed with the provider's decline code
     */
    public function charge(int $cents, string $token): string;
}
