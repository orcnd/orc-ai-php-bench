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
    /** ISO-8601 instant the order was placed. */
    public string $placedAt;

    /**
     * @param array<string, CartLine> $lines
     * @param array<string, int> $allocations
     */
    public function __construct(
        int $orderId,
        array $lines,
        ?Promotion $promotion,
        int $discount,
        array $allocations,
        string $placedAt = '1970-01-01T00:00:00+00:00'
    ) {
        $this->orderId = $orderId;
        $this->lines = $lines;
        $this->promotion = $promotion;
        $this->discount = $discount;
        $this->allocations = $allocations;
        $this->placedAt = $placedAt;
    }
}
