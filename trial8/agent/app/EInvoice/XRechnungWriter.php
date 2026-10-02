<?php
declare(strict_types=1);
namespace App\EInvoice;

use App\Billing\InvoiceLine;

/**
 * Writes EN 16931 (XRechnung) invoice lines. The customers' procurement
 * portals run the official validator, which rejects the whole invoice when a
 * line's net amount differs from quantity x unit price.
 */
final class XRechnungWriter
{
    /** @param list<InvoiceLine> $lines */
    public function lines(array $lines): string
    {
        $xml = '';
        foreach ($lines as $index => $line) {
            if ((int) round($line->quantity * $line->unitPriceCents) !== $line->totalCents) {
                throw new \DomainException(sprintf('BR-CO-10: line %d amount differs from quantity x price', $index + 1));
            }
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
