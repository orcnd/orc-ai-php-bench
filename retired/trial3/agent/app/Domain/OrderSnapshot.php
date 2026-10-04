<?php
declare(strict_types=1);
namespace App\Domain;

/**
 * The accepted purchase, as priced at checkout.
 */
final class OrderSnapshot
{
    public int $orderId;
    /** @var array<string, CartLine> */
    public array $lines;
    public ?Promotion $promotion;
    public int $discount;
    /** @var array<string, int> discount cents allocated to each merchandise line */
    public array $allocations;

    /**
     * @param array<string, CartLine> $lines
     * @param array<string, int> $allocations
     */
    public function __construct(int $orderId, array $lines, ?Promotion $promotion, int $discount, array $allocations)
    {
        $this->orderId = $orderId;
        $this->lines = $lines;
        $this->promotion = $promotion;
        $this->discount = $discount;
        $this->allocations = $allocations;
    }
}
