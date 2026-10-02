<?php
declare(strict_types=1);
namespace App\Billing;

use App\Time\TimeEntry;

final class InvoiceLineFactory
{
    /**
     * The billed quantity is the duration in hours rounded to 2 decimals, and
     * the line total is exactly quantity x hourly rate. E-invoices
     * (XRechnung/ZUGFeRD, EN 16931 rule BR-CO-10 family) are rejected by the
     * customers' portals when quantity x unit price != line total, so the total
     * must not be computed from the raw minutes. See docs/adr/0004 and closed
     * issue #87.
     */
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
