<?php
declare(strict_types=1);
namespace App\Refunds;

use App\Domain\LineType;
use App\Domain\PromotionCatalog;
use App\Ledger\Ledger;
use App\Notifications\Outbox;
use App\Orders\OrderRepository;
use App\Pricing\DiscountAllocator;
use App\Pricing\DiscountCalculator;
use App\Support\Clock;

final class CancellationService
{
    private OrderRepository $orders;
    private PromotionCatalog $promotions;
    private DiscountCalculator $calculator;
    private DiscountAllocator $allocator;
    private Ledger $ledger;
    private Outbox $outbox;
    private Clock $clock;

    public function __construct(
        OrderRepository $orders,
        PromotionCatalog $promotions,
        DiscountCalculator $calculator,
        DiscountAllocator $allocator,
        Ledger $ledger,
        Outbox $outbox,
        Clock $clock
    ) {
        $this->orders = $orders;
        $this->promotions = $promotions;
        $this->calculator = $calculator;
        $this->allocator = $allocator;
        $this->ledger = $ledger;
        $this->outbox = $outbox;
        $this->clock = $clock;
    }

    /**
     * @param array<int|string, string|int> $lineIds line ids, or line id => unit count
     * @return array{order_id: int, refund_cents: int, status: string}
     */
    public function cancel(int $orderId, array $lineIds, string $requestId): array
    {
        if (isset($this->orders->responses[$requestId])) {
            return $this->orders->responses[$requestId];
        }
        $snapshot = $this->orders->find($orderId);
        // Re-price with the current campaign so the refund matches the catalogue.
        $promotion = $snapshot->promotion === null ? null : $this->promotions->find($snapshot->promotion->code);
        $discount = $this->calculator->discount($snapshot->lines, $promotion);
        $weights = [];
        foreach ($snapshot->lines as $id => $line) {
            if ($line->type === LineType::MERCH) {
                $weights[$id] = $line->total();
            }
        }
        $allocations = $this->allocator->allocate($discount, $weights);
        $amount = 0;
        foreach ($lineIds as $id) {
            if (!isset($snapshot->lines[$id])) {
                throw new \InvalidArgumentException('Unknown line ' . $id);
            }
            if (isset($this->orders->cancelledLines[$orderId][$id])) {
                continue;
            }
            $this->orders->cancelledLines[$orderId][$id] = true;
            $line = $snapshot->lines[$id];
            if ($line->type === LineType::SHIPPING) {
                // Delivery charges are never refundable (docs/REFUNDS.md, Cancellations).
                continue;
            }
            $amount += $line->total() - ($allocations[$id] ?? 0);
        }
        $response = [
            'order_id' => $orderId,
            'refund_cents' => $amount,
            'status' => $amount > 0 ? 'refunded' : 'nothing_to_refund',
        ];
        $this->outbox->publish('refund.issued', [
            'order_id' => $orderId,
            'request_id' => $requestId,
            'refund_cents' => $amount,
        ]);
        $this->ledger->record($orderId, $requestId, $amount);
        $this->orders->responses[$requestId] = $response;
        return $response;
    }
}
