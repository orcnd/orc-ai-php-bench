<?php
declare(strict_types=1);
namespace App\Pricing;

final class Coupon
{
    public string $code;
    /** Percentage in basis points (1500 = 15%). */
    public int $percentBp;
    /** Minimum order subtotal in cents, 0 = none. */
    public int $minimumCents;

    public function __construct(string $code, int $percentBp, int $minimumCents = 0)
    {
        $this->code = $code;
        $this->percentBp = $percentBp;
        $this->minimumCents = $minimumCents;
    }

    /** Discount in cents for a subtotal; commercial rounding (half up). */
    public function discountFor(int $subtotalCents): int
    {
        if ($subtotalCents < $this->minimumCents) {
            return 0;
        }
        return (int) ($subtotalCents * $this->percentBp / 10000);
    }
}
