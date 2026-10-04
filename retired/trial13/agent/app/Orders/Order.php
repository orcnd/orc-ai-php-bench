<?php
declare(strict_types=1);
namespace App\Orders;

final class Order
{
    public string $number;
    public string $channel;
    /** @var list<OrderLine> */
    public array $lines;
    public int $shippingCents;

    /** @param list<OrderLine> $lines */
    public function __construct(string $number, string $channel, array $lines, int $shippingCents)
    {
        $this->number = $number;
        $this->channel = $channel;
        $this->lines = $lines;
        $this->shippingCents = $shippingCents;
    }

    public function merchandiseCents(): int
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $sum += $line->lineTotalCents - $line->discountCents;
        }
        return $sum;
    }
}
