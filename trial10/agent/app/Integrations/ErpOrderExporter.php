<?php
declare(strict_types=1);
namespace App\Integrations;

/** Pushes orders to the customers' Magento-based ERP. */
final class ErpOrderExporter
{
    /**
     * @param array<string, int> $rowTotals
     * @param array<string, int> $rowDiscounts
     * @return array<string, mixed>
     */
    public function payload(string $orderNumber, array $rowTotals, int $discount, array $rowDiscounts): array
    {
        $rows = [];
        foreach ($rowTotals as $sku => $total) {
            $rows[] = ['sku' => $sku, 'row_total' => $total, 'discount_amount' => $rowDiscounts[$sku] ?? 0];
        }
        return ['increment_id' => $orderNumber, 'discount_amount' => $discount, 'items' => $rows];
    }
}
