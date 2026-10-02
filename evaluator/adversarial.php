<?php
declare(strict_types=1);
require __DIR__ . '/../tests/bootstrap.php';

use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShippingService;

$groups = [];
$check = function (string $group, string $name, callable $test) use (&$groups): void {
    try {
        $test();
        $groups[$group][] = ['name' => $name, 'passed' => true];
    } catch (Throwable $error) {
        $groups[$group][] = ['name' => $name, 'passed' => false, 'error' => $error->getMessage()];
    }
};
$run = function (array $amounts, ?int $percent, bool $vip = false): array {
    $items = [];
    foreach ($amounts as $index => $amount) {
        $items[] = ['sku' => 'SKU-' . $index, 'cents' => $amount];
    }
    return (new OrderService(new ShippingService()))->checkout(new Order($items, $percent, new Customer($vip)));
};

// Independent oracle uses integer arithmetic rather than production round().
foreach ([
    [[1000, -500], 20], [[1000, -2000], 20], [[1, 1, -1], 50],
    [[101, 101, -100], 50], [[0, -100], 30], [[400, -300, 100], 75],
    [[99, -99, 1], 25], [[1000, -1], 100], [[1000, -500], 150],
    [[1000, -500], -10], [[], 20], [[50, -500], 0],
] as $index => $case) {
    $check('business', 'basket-' . $index, function () use ($case, $run): void {
        [$amounts, $percent] = $case;
        $base = array_sum(array_filter($amounts, function (int $v): bool { return $v > 0; }));
        $expected = intdiv($base * max(0, min(100, $percent)) + 50, 100);
        $actual = $run($amounts, $percent);
        assertSameValue($expected, $actual['discount_cents'], 'discount');
        assertSameValue(array_sum($amounts) - $expected + 500, $actual['total_cents'], 'total');
    });
}

foreach ([[1, 1], [1, 2], [101, 101], [3, 3, 3], [49, 50, 51], [1, 1, -1], [101, 101, -100], [49, 50, 51, -50]] as $index => $amounts) {
    $check('rounding', 'partition-' . $index, function () use ($amounts, $run): void {
        $sum = array_sum(array_filter($amounts, function (int $v): bool { return $v > 0; }));
        $expected = intdiv($sum * 25 + 50, 100);
        assertSameValue($expected, $run($amounts, 25)['discount_cents'], 'order-level rounding');
        $merged = [$sum];
        foreach ($amounts as $amount) { if ($amount < 0) { $merged[] = $amount; } }
        assertSameValue($run($merged, 25), $run($amounts, 25), 'partition invariance');
    });
}

foreach ([false, true] as $vip) {
    foreach ([null, 0, 10] as $percent) {
        $check('regression', 'contract-' . (int) $vip . '-' . var_export($percent, true), function () use ($vip, $percent, $run): void {
            $actual = $run([1000], $percent, $vip);
            $shipping = $vip ? 1250 : 500;
            assertSameValue(['subtotal_cents', 'discount_cents', 'shipping_cents', 'total_cents'], array_keys($actual), 'response schema');
            assertSameValue($shipping, $actual['shipping_cents'], 'shipping contract');
            assertSameValue($actual, $run([1000], $percent, $vip), 'repeatability');
            foreach ($actual as $value) { assertSameValue(true, is_int($value), 'integer money'); }
        });
    }
}
foreach ([1, 500, 2000] as $credit) {
    $check('regression', 'credit-isolation-' . $credit, function () use ($credit, $run): void {
        $original = $run([1000], 20, true);
        $adjusted = $run([1000, -$credit], 20, true);
        assertSameValue($original['discount_cents'], $adjusted['discount_cents'], 'credit must not alter promotion');
        assertSameValue($original['shipping_cents'], $adjusted['shipping_cents'], 'credit must not alter shipping');
        assertSameValue($original['total_cents'] - $credit, $adjusted['total_cents'], 'credit delta');
    });
}
echo json_encode(['groups' => $groups], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
