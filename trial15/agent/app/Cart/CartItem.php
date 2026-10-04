<?php
declare(strict_types=1);
namespace App\Cart;

final class CartItem
{
    public string $sku;
    public int $quantity;
    public int $unitCents;
    /** Grams; 0 for downloads. */
    public int $weight;
    public bool $virtual;

    public function __construct(string $sku, int $quantity, int $unitCents, int $weight, bool $virtual = false)
    {
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->unitCents = $unitCents;
        $this->weight = $weight;
        $this->virtual = $virtual;
    }
}
