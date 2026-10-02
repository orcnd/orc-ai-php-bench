<?php
declare(strict_types=1);
namespace App;

use App\Checkout\CheckoutService;
use App\Domain\PromotionCatalog;
use App\Http\CancellationController;
use App\Ledger\Ledger;
use App\Notifications\Outbox;
use App\Orders\OrderRepository;
use App\Pricing\DiscountAllocator;
use App\Pricing\DiscountCalculator;
use App\Refunds\CancellationService;
use App\Storage\FileStore;
use App\Support\Clock;
use App\Support\SystemClock;

/**
 * Composition root, built once per HTTP request by public/index.php (PHP-FPM:
 * one request per process lifetime, nothing in memory survives). Deployment
 * scripts read these public properties; keep their names and types.
 */
final class Kernel
{
    public FileStore $store;
    public Clock $clock;
    public PromotionCatalog $promotions;
    public OrderRepository $orders;
    public Ledger $ledger;
    public Outbox $outbox;
    public CheckoutService $checkout;
    public CancellationController $cancellations;

    public function __construct(string $storageDirectory, ?Clock $clock = null)
    {
        $this->store = new FileStore($storageDirectory);
        $this->clock = $clock ?? new SystemClock();
        $this->promotions = new PromotionCatalog();
        $this->orders = new OrderRepository($this->store);
        $this->ledger = new Ledger($this->store);
        $this->outbox = new Outbox($this->store);
        $calculator = new DiscountCalculator();
        $allocator = new DiscountAllocator();
        $this->checkout = new CheckoutService($this->promotions, $calculator, $allocator, $this->orders, $this->clock);
        $this->cancellations = new CancellationController(
            new CancellationService($this->orders, $this->ledger, $this->outbox, $this->clock)
        );
    }
}
