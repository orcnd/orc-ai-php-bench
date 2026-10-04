<?php
declare(strict_types=1);
namespace App\Coupons;

final class Coupon
{
    public string $code;
    /** Last valid day (Y-m-d), inclusive, in the store's timezone. */
    public string $expiresOn;

    public function __construct(string $code, string $expiresOn)
    {
        $this->code = $code;
        $this->expiresOn = $expiresOn;
    }
}
