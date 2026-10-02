<?php
declare(strict_types=1);
namespace App\Domain;

final class Cart
{
    /** @var array<string, CartLine> */
    private array $lines = [];

    public function add(CartLine $line): void
    {
        if (isset($this->lines[$line->id])) {
            throw new \InvalidArgumentException('Duplicate line ' . $line->id);
        }
        $this->lines[$line->id] = $line;
    }

    public function changeQuantity(string $id, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        $this->line($id)->quantity = $quantity;
    }

    public function reprice(string $id, int $unitCents): void
    {
        $this->line($id)->unitCents = $unitCents;
    }

    public function remove(string $id): void
    {
        unset($this->lines[$id]);
    }

    public function line(string $id): CartLine
    {
        if (!isset($this->lines[$id])) {
            throw new \OutOfBoundsException('No line ' . $id);
        }
        return $this->lines[$id];
    }

    /** @return array<string, CartLine> */
    public function lines(): array
    {
        return $this->lines;
    }
}
