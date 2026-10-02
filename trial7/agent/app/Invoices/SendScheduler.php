<?php
declare(strict_types=1);
namespace App\Invoices;

final class SendScheduler
{
    /**
     * UTC instant at which an invoice scheduled for $date at $time in the
     * customer's timezone is e-mailed.
     */
    public function sendAtUtc(string $date, string $time, string $timezone, \DateTimeImmutable $now): string
    {
        $offset = (new \DateTimeZone($timezone))->getOffset($now);
        $local = new \DateTimeImmutable($date . ' ' . $time . ':00', new \DateTimeZone('UTC'));
        return $local->modify(sprintf('%+d seconds', -$offset))->format('Y-m-d\TH:i:s\Z');
    }
}
