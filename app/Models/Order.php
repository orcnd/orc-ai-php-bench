<?php

declare(strict_types=1);

namespace App\Models;

final class Order
{
    /** @var array<int, array{sku: string, cents: int}> */
    private array $items;
    private ?int $couponPercent;
    private Customer $customer;

    /** @param array<int, array{sku: string, cents: int}> $items */
    public function __construct(array $items, ?int $couponPercent, Customer $customer)
    {
        $this->items = $items;
        $this->couponPercent = $couponPercent;
        $this->customer = $customer;
    }

    /** @return array<int, array{sku: string, cents: int}> */
    public function items(): array
    {
        return $this->items;
    }

    public function couponPercent(): ?int
    {
        return $this->couponPercent;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }
}
