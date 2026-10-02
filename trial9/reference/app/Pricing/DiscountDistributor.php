<?php
declare(strict_types=1);
namespace App\Pricing;

final class DiscountDistributor
{
    /**
     * Spread an order-level discount over the order rows in proportion to
     * their totals, carrying each row's rounding difference into the next
     * row (the Magento-compatible algorithm). The ERP import recomputes the
     * rows with exactly this algorithm and rejects documents whose rows
     * differ, so identical rows can legitimately get different shares
     * (e.g. 39.92 / 39.91 / 39.92). See docs/integrations/erp.md.
     *
     * @param array<string, int> $rowTotals
     * @return array<string, int>
     */
    public function distribute(int $discount, array $rowTotals): array
    {
        $total = array_sum($rowTotals);
        $shares = [];
        $carry = 0.0;
        foreach ($rowTotals as $row => $amount) {
            $exact = $total > 0 ? $discount * $amount / $total + $carry : 0.0;
            $rounded = (int) round($exact);
            $carry = $exact - $rounded;
            $shares[$row] = $rounded;
        }
        return $shares;
    }
}
