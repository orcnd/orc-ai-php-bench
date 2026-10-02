<?php
declare(strict_types=1);
namespace App\Reports;

final class Transaction
{
    public int $id;
    /** Local date-time "Y-m-d H:i:s". */
    public string $bookedAt;
    public int $amountCents;

    public function __construct(int $id, string $bookedAt, int $amountCents)
    {
        $this->id = $id;
        $this->bookedAt = $bookedAt;
        $this->amountCents = $amountCents;
    }
}
