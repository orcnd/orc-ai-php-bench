<?php
declare(strict_types=1);
namespace App\Billing;

use App\Time\TimeEntry;

final class InvoiceLineFactory
{

    public function fromTimeEntry(TimeEntry $entry): InvoiceLine
    {
        $hours = round($entry->minutes / 60, 2);
        return new InvoiceLine(
            sprintf('Work on %s', $entry->date),
            $hours,
            $entry->rateCents,
            (int) round($hours * $entry->rateCents)
        );
    }
}
