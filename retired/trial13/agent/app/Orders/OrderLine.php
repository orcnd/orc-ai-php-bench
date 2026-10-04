<?php
declare(strict_types=1);
namespace App\Orders;

final class OrderLine
{
    public string $sku;
    public int $quantity;
    public int $unitPriceCents;
    public int $lineTotalCents;
    public int $discountCents;

    public function __construct(string $sku, int $quantity, int $unitPriceCents, int $lineTotalCents, int $discountCents = 0)
    {
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->unitPriceCents = $unitPriceCents;
        $this->lineTotalCents = $lineTotalCents;
        $this->discountCents = $discountCents;
    }
}
