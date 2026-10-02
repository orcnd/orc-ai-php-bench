<?php
declare(strict_types=1);
namespace App;

use App\Checkout\CheckoutService;
use App\Domain\PromotionCatalog;
use App\Http\CancellationController;
use App\Ledger\Ledger;
use App\Orders\OrderRepository;
use App\Pricing\DiscountAllocator;
use App\Pricing\DiscountCalculator;
use App\Refunds\CancellationService;

/**
 * Composition root. Deployment scripts and the HTTP front controller read
 * these public properties; keep their names and types.
 */
final class Kernel
{
    public PromotionCatalog $promotions;
    public OrderRepository $orders;
    public Ledger $ledger;
    public CheckoutService $checkout;
    public CancellationController $cancellations;

    public function __construct()
    {
        $this->promotions = new PromotionCatalog();
        $this->orders = new OrderRepository();
        $this->ledger = new Ledger();
        $calculator = new DiscountCalculator();
        $allocator = new DiscountAllocator();
        $this->checkout = new CheckoutService($this->promotions, $calculator, $allocator, $this->orders);
        $this->cancellations = new CancellationController(
            new CancellationService($this->orders, $this->ledger)
        );
    }
}
