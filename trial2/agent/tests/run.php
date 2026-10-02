<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';
$repository = new App\OrderRepository();
$repository->orders[7] = ['lines' => [1000, -500], 'promotion' => 20, 'shipping' => 500];
$controller = new App\OrderController(new App\RefundService($repository, new App\PricingPipeline()));
$actual = $controller->cancel(7);
if ($actual !== ['order_id' => 7, 'refund_cents' => 300]) {
    fwrite(STDERR, 'Expected refund 300 cents; got ' . json_encode($actual) . PHP_EOL);
    exit(1);
}
echo "Public regression passed\n";
