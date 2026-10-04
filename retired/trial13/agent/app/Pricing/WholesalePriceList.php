<?php
declare(strict_types=1);
namespace App\Pricing;

/** Current wholesale prices (new orders only). Unit prices in cents. */
final class WholesalePriceList
{
    /** @var array<string, int> */
    private array $prices = ['PAL-EURO' => 33334, 'BOX-S' => 125, 'BOX-L' => 349];

    public function unitPrice(string $sku): int
    {
        if (!isset($this->prices[$sku])) {
            throw new \OutOfBoundsException('Unknown SKU ' . $sku);
        }
        return $this->prices[$sku];
    }

    public function lineTotal(string $sku, int $quantity): int
    {
        return $this->unitPrice($sku) * $quantity;
    }
}
