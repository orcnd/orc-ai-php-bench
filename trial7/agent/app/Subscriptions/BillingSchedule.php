<?php
declare(strict_types=1);
namespace App\Subscriptions;

final class BillingSchedule
{
    /**
     * Billing date of the given period (0 = the anchor itself) for a monthly
     * subscription. Customers are billed on the anchor's day of month; in
     * shorter months, on that month's last day.
     */
    public function billingDate(\DateTimeImmutable $anchor, int $period): \DateTimeImmutable
    {
        return $anchor->modify(sprintf('+%d month', $period));
    }
}
