<?php
declare(strict_types=1);
namespace App\Billing;

final class InvoiceLine
{
    public string $description;
    public float $quantity;
    public int $unitPriceCents;
    public int $totalCents;

    public function __construct(string $description, float $quantity, int $unitPriceCents, int $totalCents)
    {
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPriceCents = $unitPriceCents;
        $this->totalCents = $totalCents;
    }
}
