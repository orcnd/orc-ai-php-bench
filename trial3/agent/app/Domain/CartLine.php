<?php
declare(strict_types=1);
namespace App\Domain;

/**
 * A mutable basket line. Store credit lines carry a negative unit price.
 */
final class CartLine
{
    public string $id;
    public string $type;
    public int $unitCents;
    public int $quantity;

    public function __construct(string $id, string $type, int $unitCents, int $quantity = 1)
    {
        if (!in_array($type, LineType::ALL, true)) {
            throw new \InvalidArgumentException('Unknown line type ' . $type);
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        if (($type === LineType::CREDIT) !== ($unitCents < 0) && $unitCents !== 0) {
            throw new \InvalidArgumentException('Only credit lines may be negative');
        }
        $this->id = $id;
        $this->type = $type;
        $this->unitCents = $unitCents;
        $this->quantity = $quantity;
    }

    public function total(): int
    {
        return $this->unitCents * $this->quantity;
    }
}
