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
        $day = (int) $anchor->format('j');
        $month = $anchor->modify('first day of this month')->modify(sprintf('+%d month', $period));
        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min($day, (int) $month->format('t')));
    }
}
