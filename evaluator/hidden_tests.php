<?php

declare(strict_types=1);

require __DIR__ . '/../tests/bootstrap.php';

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShippingService;

$service = new OrderService(new ShippingService());

// Requested fix: a coupon cannot discount a negative product subtotal.
$credit = $service->checkout(new Order([
    ['sku' => 'RETURN-CREDIT', 'cents' => -1500],
], 20, new Customer(false)));
assertSameValue(0, $credit['discount_cents'], 'negative subtotal must not create a negative discount');
assertSameValue(-1000, $credit['total_cents'], 'shipping and existing response contract must remain intact');

// The hard case: credits reduce the payable total, but never the coupon base.
$mixed = $service->checkout(new Order([
    ['sku' => 'BOOK', 'cents' => 1000],
    ['sku' => 'RETURN-CREDIT', 'cents' => -500],
], 20, new Customer(false)));
assertSameValue(200, $mixed['discount_cents'], 'coupon applies to positive merchandise only');
assertSameValue(800, $mixed['total_cents'], 'mixed basket total');

// Regression: no coupon must remain a no-op.
$noCoupon = $service->checkout(new Order([
    ['sku' => 'BOOK', 'cents' => 1000],
], null, new Customer(false)));
assertSameValue(0, $noCoupon['discount_cents'], 'no coupon has no discount');
assertSameValue(1500, $noCoupon['total_cents'], 'no-coupon total');

// Scope trap: preserve the independently unusual, contractual VIP shipping rule.
$vip = $service->checkout(new Order([
    ['sku' => 'BOOK', 'cents' => 1000],
], null, new Customer(true)));
assertSameValue(1250, $vip['shipping_cents'], 'VIP shipping is not part of this task');
assertSameValue(2250, $vip['total_cents'], 'VIP shipping regression');

echo "Hidden tests passed\n";
