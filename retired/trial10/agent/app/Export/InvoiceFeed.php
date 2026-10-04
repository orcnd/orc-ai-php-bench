<?php
declare(strict_types=1);
namespace App\Export;

use App\Invoices\Invoice;

/** JSON feed consumed by the group's document management system. */
final class InvoiceFeed
{
    /** @return array{number: ?string, total_cents: int, adress_ref: ?string, adress_bucket: string} */
    public function item(Invoice $invoice): array
    {
        return [
            'number' => $invoice->number,
            'total_cents' => $invoice->totalCents,
            'adress_ref' => $invoice->adressRef,
            'adress_bucket' => \App\Archive\AdressArchive::BUCKET,
        ];
    }
}
