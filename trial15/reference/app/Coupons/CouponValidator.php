<?php
declare(strict_types=1);
namespace App\Coupons;

use App\Support\Clock;

final class CouponValidator
{
    private Clock $clock;
    private string $storeTimezone;

    public function __construct(Clock $clock, string $storeTimezone)
    {
        $this->clock = $clock;
        $this->storeTimezone = $storeTimezone;
    }

    public function storeTimezone(): string
    {
        return $this->storeTimezone;
    }

    public function isValid(Coupon $coupon): bool
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone($this->storeTimezone))->format('Y-m-d') <= $coupon->expiresOn;
    }
}
