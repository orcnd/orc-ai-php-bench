<?php
declare(strict_types=1);
namespace App\Mail;

/**
 * Formats amounts for customer e-mails.
 *
 * The output is parsed by the accounting mailbox importer (see
 * docs/REFUNDS.md, "Downstream consumers"). Do not change the format.
 */
final class ReceiptFormatter
{
    public function money(int $cents): string
    {
        $formatted = number_format(abs($cents) / 100, 2);
        return $cents < 0 ? '(' . $formatted . ')' : $formatted;
    }

    /** @param array{order_id: int, refund_cents: int, status: string} $response */
    public function refundLine(array $response): string
    {
        return sprintf('Order #%d refund: %s EUR', $response['order_id'], $this->money($response['refund_cents']));
    }
}
