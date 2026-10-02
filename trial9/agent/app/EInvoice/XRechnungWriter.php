<?php
declare(strict_types=1);
namespace App\EInvoice;

use App\Billing\InvoiceLine;

/** Writes EN 16931 (XRechnung) invoice lines. */
final class XRechnungWriter
{
    /** @param list<InvoiceLine> $lines */
    public function lines(array $lines): string
    {
        $xml = '';
        foreach ($lines as $line) {
            $xml .= sprintf(
                "<cac:InvoiceLine><cbc:InvoicedQuantity unitCode=\"HUR\">%.2F</cbc:InvoicedQuantity>"
                . "<cbc:LineExtensionAmount>%.2F</cbc:LineExtensionAmount><cbc:PriceAmount>%.2F</cbc:PriceAmount></cac:InvoiceLine>\n",
                $line->quantity,
                $line->totalCents / 100,
                $line->unitPriceCents / 100
            );
        }
        return $xml;
    }
}
