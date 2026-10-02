<?php
declare(strict_types=1);
namespace App\Reports;

use App\Ledger\Ledger;

final class RefundReport
{
    /** @return array<int, int> refunded cents per order */
    public function perOrder(Ledger $ledger): array
    {
        $totals = [];
        foreach ($ledger->entries() as $entry) {
            $totals[$entry['order_id']] = ($totals[$entry['order_id']] ?? 0) + $entry['amount'];
        }
        ksort($totals);
        return $totals;
    }
}
