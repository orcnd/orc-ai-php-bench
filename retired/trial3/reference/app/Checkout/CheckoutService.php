<?php
declare(strict_types=1);
namespace App\Checkout;

use App\Domain\Cart;
use App\Domain\LineType;
use App\Domain\OrderSnapshot;
use App\Domain\PromotionCatalog;
use App\Orders\OrderRepository;
use App\Pricing\DiscountAllocator;
use App\Pricing\DiscountCalculator;

final class CheckoutService
{
    private PromotionCatalog $promotions;
    private DiscountCalculator $calculator;
    private DiscountAllocator $allocator;
    private OrderRepository $orders;

    public function __construct(
        PromotionCatalog $promotions,
        DiscountCalculator $calculator,
        DiscountAllocator $allocator,
        OrderRepository $orders
    ) {
        $this->promotions = $promotions;
        $this->calculator = $calculator;
        $this->allocator = $allocator;
        $this->orders = $orders;
    }

    public function place(int $orderId, Cart $cart, ?string $promotionCode = null): OrderSnapshot
    {
        $promotion = null;
        if ($promotionCode !== null) {
            $promotion = $this->promotions->find($promotionCode);
            if ($promotion === null) {
                throw new \InvalidArgumentException('Unknown promotion ' . $promotionCode);
            }
            $promotion = clone $promotion;
        }
        $lines = [];
        foreach ($cart->lines() as $id => $line) {
            $lines[$id] = clone $line;
        }
        $discount = $this->calculator->discount($lines, $promotion);
        $weights = [];
        foreach ($lines as $id => $line) {
            if ($line->type === LineType::MERCH) {
                $weights[$id] = $line->total();
            }
        }
        $snapshot = new OrderSnapshot($orderId, $lines, $promotion, $discount, $this->allocator->allocate($discount, $weights));
        $this->orders->save($snapshot);
        return $snapshot;
    }
}
