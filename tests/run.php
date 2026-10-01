<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShippingService;

$service = new OrderService(new ShippingService());
$result = $service->checkout(new Order([
    ['sku' => 'BOOK', 'cents' => 1000],
], 10, new Customer(false)));

assertSameValue(1000, $result['subtotal_cents'], 'subtotal must stay exposed');
assertSameValue(100, $result['discount_cents'], 'normal percentage coupon');
assertSameValue(500, $result['shipping_cents'], 'standard shipping');
assertSameValue(1400, $result['total_cents'], 'normal checkout total');

$credit = $service->checkout(new Order([
    ['sku' => 'RETURN-CREDIT', 'cents' => -1500],
], 20, new Customer(false)));
assertSameValue(0, $credit['discount_cents'], 'coupon must not become a negative discount');
assertSameValue(-1000, $credit['total_cents'], 'credit total preserves shipping');

echo "Public tests passed\n";
