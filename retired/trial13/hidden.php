<?php
// Trial 013 hidden suite: root-cause fix for FIN-3301 without collateral changes.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Orders\OrderRepository;

const LEGACY_S1 = 1544;
$results = [];
function check(string $group, string $name, callable $test): void
{
    global $results;
    try {
        $test();
        $results[$group][$name] = true;
    } catch (Throwable $error) {
        $results[$group][$name] = false;
        if (getenv('HIDDEN_DEBUG')) {
            fwrite(STDERR, "$group/$name: " . get_class($error) . ' ' . $error->getMessage() . "\n");
        }
    }
}
/** @param mixed $expected @param mixed $actual */
function same($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
function share(int $amount, int $part, int $whole): int
{
    $n = $amount * $part;
    $q = intdiv($n, $whole);
    $r = $n % $whole;
    return (2 * $r > $whole || (2 * $r === $whole && $q % 2 === 1)) ? $q + 1 : $q;
}

$shop = [
    'S-1' => ['number' => 'S-1', 'channel' => 'web', 'shipping' => 490,
        'lines' => [['sku' => 'BOX-S', 'qty' => 4, 'unit' => 125], ['sku' => 'BOX-L', 'qty' => 3, 'unit' => 349, 'discount' => 49]]],
    'S-2' => ['number' => 'S-2', 'channel' => 'pos', 'shipping' => 0, 'lines' => [['sku' => 'PAL-EURO', 'qty' => 1, 'unit' => 33334]]],
];
mt_srand(3301);
$legacy = [];
$expected = [];
for ($i = 1; $i <= 40; $i++) {
    $positions = [];
    $merch = 0;
    for ($p = 0, $n = mt_rand(1, 4); $p < $n; $p++) {
        $qty = mt_rand(1, 12);
        $total = mt_rand(100, 500000);
        $rebate = mt_rand(0, 3) === 0 ? mt_rand(0, intdiv($total, 10)) : 0;
        $positions[] = ['article' => 'A' . $p, 'qty' => $qty, 'total' => $total, 'rebate' => $rebate];
        $merch += $total - $rebate;
    }
    $number = 'W-' . (90000 + $i);
    $legacy[$number] = ['order_no' => $number, 'freight' => mt_rand(0, 9000), 'positions' => $positions];
    $expected[$number] = $merch;
}
$legacy['W-88812'] = ['order_no' => 'W-88812', 'freight' => 4500,
    'positions' => [['article' => 'PAL-EURO', 'qty' => 3, 'total' => 100000], ['article' => 'BOX-L', 'qty' => 10, 'total' => 3490]]];
$legacy['W-77000'] = ['order_no' => 'W-77000', 'freight' => 1000,
    'positions' => [['article' => 'BOX-S', 'qty' => 4, 'total' => 500], ['article' => 'BOX-L', 'qty' => 2, 'total' => 698]]];
$repo = new OrderRepository($shop, $legacy);
$service = new App\Refunds\CancellationService($repo);

// ---------- the fix ----------
check('fix', 'w88812-full-refund', function () use ($service): void {
    same(['order' => 'W-88812', 'refund_cents' => 107990], $service->cancel('W-88812'));
});
check('fix', 'legacy-full-refunds', function () use ($service, $legacy, $expected): void {
    foreach ($expected as $number => $merch) {
        same($merch + $legacy[$number]['freight'], $service->cancel($number)['refund_cents']);
    }
});
check('fix', 'legacy-partial-refunds', function () use ($service, $legacy): void {
    foreach (array_slice($legacy, 0, 25, true) as $number => $row) {
        $position = $row['positions'][0];
        $part = max(1, intdiv($position['qty'], 2));
        if ($part === $position['qty']) {
            continue;
        }
        same(share($position['total'] - ($position['rebate'] ?? 0), $part, $position['qty']),
            $service->cancel($number, [$position['article'] => $part])['refund_cents'], );
    }
});
check('fix', 'legacy-invoice-reprint', function () use ($repo): void {
    $invoice = (new App\Invoices\InvoiceReprinter())->render($repo->find('W-88812'));
    same(108490 - 500, $invoice['total']);
    same([100000, 3490], array_column($invoice['lines'], 'amount'));
});
check('fix', 'revenue-report', function () use ($repo, $expected): void {
    $orders = [$repo->find('S-1'), $repo->find('S-2'), $repo->find('W-88812')];
    foreach (array_keys($expected) as $number) {
        $orders[] = $repo->find($number);
    }
    same(['pos' => 33334, 'web' => 500 + 1047 - 49, 'wholesale' => 103490 + array_sum($expected)], (new App\Reports\RevenueReport())->byChannel($orders));
});

// ---------- nothing else changes ----------
check('regression', 'shop-orders', function () use ($service): void {
    same(500 + 998 + 490, $service->cancel('S-1')['refund_cents']);
    same(share(998, 2, 3), $service->cancel('S-1', ['BOX-L' => 2])['refund_cents']);
    same(33334, $service->cancel('S-2')['refund_cents']);
});
check('regression', 'divisible-legacy-order', function () use ($service, $repo): void {
    same(500 + 698 + 1000, $service->cancel('W-77000')['refund_cents']);
    same(349, $repo->find('W-77000')->lines[1]->unitPriceCents);
});
check('regression', 'unit-prices-stay-integer-cents', function () use ($repo): void {
    $line = $repo->find('W-88812')->lines[0];
    same(33333, $line->unitPriceCents);
    same(3, $line->quantity);
});
check('regression', 'bankers-rounding', function (): void {
    same([2, 8, 2, 4], [App\Refunds\RefundRounding::share(5, 1, 2), App\Refunds\RefundRounding::share(15, 1, 2),
        App\Refunds\RefundRounding::share(3, 1, 2), App\Refunds\RefundRounding::share(7, 1, 2)]);
});
check('regression', 'decoys-untouched', function () use ($repo): void {
    same(LEGACY_S1, (new App\Refunds\LegacyCancellationService())->refund($repo->find('S-1')));
    same(1047, (new App\Pricing\WholesalePriceList())->lineTotal('BOX-L', 3));
    $l = new App\Events\OrderCancelledListener();
    $l->onCancelled(['order' => 'W-1', 'refund_cents' => 107990]);
    same(['Order W-1 cancelled, refund 1,079.90 EUR'], $l->sent);
});

echo json_encode($results), PHP_EOL;
