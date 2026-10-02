<?php
// Trial 004 hidden suite. Must run on PHP 7.4+. Prints one JSON object.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Domain\Cart;
use App\Domain\CartLine;
use App\Domain\LineType;
use App\Domain\Promotion;
use App\Kernel;

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
        throw new RuntimeException('expected ' . json_encode($expected) . ' got ' . json_encode($actual));
    }
}
function throwsInvalid(callable $body): void
{
    try {
        $body();
    } catch (InvalidArgumentException $expected) {
        return;
    }
    throw new RuntimeException('expected InvalidArgumentException');
}

// ---------- independent oracle ----------
/** @param list<array{0:string,1:string,2:int,3?:int}> $lines @param array{0:string,1:int}|null $promo */
function oracle(array $lines, ?array $promo): array
{
    $merch = [];
    $qty = [];
    $credit = 0;
    foreach ($lines as $l) {
        $total = $l[2] * ($l[3] ?? 1);
        $qty[$l[0]] = $l[3] ?? 1;
        if ($l[1] === 'merch') { $merch[$l[0]] = $total; }
        if ($l[1] === 'credit') { $credit += $total; }
    }
    $base = array_sum($merch);
    $discount = 0;
    if ($promo !== null) {
        $discount = $promo[0] === 'fixed' ? min($promo[1], $base) : intdiv($base * $promo[1] + 50, 100);
    }
    $alloc = [];
    $rem = [];
    $i = 0;
    foreach ($merch as $id => $w) {
        // GMP keeps the oracle exact for wholesale-sized orders.
        [$q, $r] = $base > 0 ? gmp_div_qr(gmp_mul($discount, $w), $base) : [gmp_init(0), gmp_init(0)];
        $alloc[$id] = gmp_intval($q);
        $rem[] = [gmp_intval($r), $i++, $id];
    }
    $left = $base > 0 ? $discount - array_sum($alloc) : 0;
    usort($rem, function (array $a, array $b): int { return $a[0] !== $b[0] ? $b[0] - $a[0] : $a[1] - $b[1]; });
    for ($k = 0; $k < $left; $k++) { $alloc[$rem[$k][2]]++; }
    return ['discount' => $discount, 'alloc' => $alloc, 'merch' => $merch, 'credit' => $credit, 'qty' => $qty];
}
/** @param array<string,int> $cancelled units */
function entitlement(array $o, array $cancelled): int
{
    $sum = $o['credit'];
    foreach ($o['merch'] as $id => $total) {
        if (isset($cancelled[$id])) {
            $sum += gmp_intval(gmp_div_q(gmp_mul($total - $o['alloc'][$id], $cancelled[$id]), $o['qty'][$id]));
        }
    }
    return max(0, $sum);
}
function expectedRefund(array $o, array $sequence): array
{
    $cancelled = [];
    $out = [];
    foreach ($sequence as $request) {
        $before = entitlement($o, $cancelled);
        foreach ($request as $key => $value) {
            $id = is_int($key) ? $value : $key;
            $cancelled[$id] = is_int($key) ? $o['qty'][$id] : ($cancelled[$id] ?? 0) + $value;
        }
        $out[] = entitlement($o, $cancelled) - $before;
    }
    return $out;
}

// ---------- fixtures ----------
/** @param list<array{0:string,1:string,2:int,3?:int}> $lines */
function cart(array $lines): Cart
{
    $cart = new Cart();
    foreach ($lines as $l) { $cart->add(new CartLine($l[0], $l[1], $l[2], $l[3] ?? 1)); }
    return $cart;
}
function kernel(?array $promo): Kernel
{
    $kernel = new Kernel();
    if ($promo !== null) { $kernel->promotions->add(new Promotion('P', $promo[0], $promo[1])); }
    return $kernel;
}
function place(Kernel $kernel, int $orderId, array $lines, ?array $promo): App\Domain\OrderSnapshot
{
    return $kernel->checkout->place($orderId, cart($lines), $promo === null ? null : 'P');
}
function response(int $orderId, int $amount): array
{
    return ['order_id' => $orderId, 'refund_cents' => $amount, 'status' => $amount > 0 ? 'refunded' : 'nothing_to_refund'];
}
function allIds(array $lines): array
{
    return array_map(function (array $l): string { return $l[0]; }, $lines);
}
function ledgerSum(Kernel $kernel, int $orderId): int
{
    $sum = 0;
    foreach ($kernel->ledger->entries() as $entry) {
        if ($entry['order_id'] === $orderId) { $sum += $entry['amount']; }
    }
    return $sum;
}

// ---------- public ----------
check('public', 'fin-2291', function (): void {
    $lines = [['shirt', 'merch', 1000], ['return', 'credit', -500], ['delivery', 'shipping', 500]];
    $k = kernel(['percent', 20]);
    place($k, 7, $lines, ['percent', 20]);
    same(response(7, 300), $k->cancellations->cancel(7, allIds($lines), 'k1'));
});

// ---------- discount (order-level pricing, full cancellation) ----------
$discountCases = [
    'credit-not-in-base' => [[['a', 'merch', 2000], ['c', 'credit', -1500]], ['percent', 10]],
    'half-up' => [[['a', 'merch', 125]], ['percent', 2]],
    'below-half' => [[['a', 'merch', 124]], ['percent', 2]],
    'quantity' => [[['a', 'merch', 333, 3], ['s', 'shipping', 499]], ['percent', 15]],
    'fixed-capped' => [[['a', 'merch', 700], ['c', 'credit', -200]], ['fixed', 900]],
    'fixed-with-credit' => [[['a', 'merch', 1000], ['c', 'credit', -900]], ['fixed', 500]],
    'hundred-percent' => [[['a', 'merch', 999], ['s', 'shipping', 300]], ['percent', 100]],
    'shipping-only-merch-zero' => [[['s', 'shipping', 500], ['a', 'merch', 0]], ['percent', 50]],
    'credit-exceeds' => [[['a', 'merch', 300], ['c', 'credit', -1000]], ['percent', 10]],
];
foreach ($discountCases as $name => [$lines, $promo]) {
    check('discount', $name, function () use ($lines, $promo): void {
        $o = oracle($lines, $promo);
        $k = kernel($promo);
        $snapshot = place($k, 1, $lines, $promo);
        same($o['discount'], $snapshot->discount);
        same(response(1, expectedRefund($o, [allIds($lines)])[0]), $k->cancellations->cancel(1, allIds($lines), 'r'));
    });
}

// ---------- allocation ----------
$allocationCases = [
    'three-way-split' => [[['a', 'merch', 100], ['b', 'merch', 100], ['c', 'merch', 100]], ['fixed', 100]],
    'tie-first-wins' => [[['a', 'merch', 100], ['b', 'merch', 100], ['c', 'merch', 100]], ['fixed', 200]],
    'largest-remainder-not-first' => [[['a', 'merch', 300], ['b', 'merch', 199], ['c', 'merch', 101]], ['fixed', 5]],
    'many-small' => [[['a', 'merch', 1], ['b', 'merch', 1], ['c', 'merch', 1], ['d', 'merch', 1], ['e', 'merch', 1], ['f', 'merch', 1], ['g', 'merch', 3]], ['percent', 50]],
    'skip-non-merch' => [[['s', 'shipping', 999], ['a', 'merch', 333], ['c', 'credit', -50], ['b', 'merch', 667]], ['percent', 33]],
    'quantities' => [[['a', 'merch', 199, 3], ['b', 'merch', 1, 7]], ['fixed', 101]],
];
foreach ($allocationCases as $name => [$lines, $promo]) {
    check('allocation', $name, function () use ($lines, $promo): void {
        $o = oracle($lines, $promo);
        $k = kernel($promo);
        $snapshot = place($k, 1, $lines, $promo);
        $actual = $snapshot->allocations;
        ksort($actual);
        $expected = $o['alloc'];
        ksort($expected);
        same($expected, $actual);
        same($o['discount'], array_sum($actual));
    });
}
check('allocation', 'per-line-partial-refunds', function (): void {
    $lines = [['a', 'merch', 300], ['b', 'merch', 199], ['c', 'merch', 101]];
    $promo = ['fixed', 5];
    $o = oracle($lines, $promo);
    $k = kernel($promo);
    place($k, 1, $lines, $promo);
    $seq = [['a'], ['b'], ['c']];
    $expected = expectedRefund($o, $seq);
    foreach ($seq as $i => $ids) {
        same(response(1, $expected[$i]), $k->cancellations->cancel(1, $ids, 'r' . $i));
    }
});

// ---------- partial cancellation ----------
check('partial', 'shipping-never-refunded', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['s', 'shipping', 700]], null);
    same(response(1, 0), $k->cancellations->cancel(1, ['s'], 'r1'));
    same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'r2'));
    same(1000, ledgerSum($k, 1));
});
check('partial', 'credit-consumed-first', function (): void {
    $lines = [['a', 'merch', 1000], ['b', 'merch', 1000], ['c', 'credit', -1500]];
    $k = kernel(null);
    place($k, 1, $lines, null);
    same(response(1, 0), $k->cancellations->cancel(1, ['a'], 'r1'));
    same(response(1, 500), $k->cancellations->cancel(1, ['b'], 'r2'));
});
check('partial', 'credit-line-alone', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['c', 'credit', -400]], null);
    same(response(1, 0), $k->cancellations->cancel(1, ['c'], 'r1'));
    same(response(1, 600), $k->cancellations->cancel(1, ['a'], 'r2'));
});
check('partial', 'already-cancelled-ignored', function (): void {
    $k = kernel(['percent', 10]);
    place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 500]], ['percent', 10]);
    same(response(1, 900), $k->cancellations->cancel(1, ['a'], 'r1'));
    same(response(1, 450), $k->cancellations->cancel(1, ['a', 'b'], 'r2'));
    same(response(1, 0), $k->cancellations->cancel(1, ['a', 'b'], 'r3'));
    same(1350, ledgerSum($k, 1));
});
check('partial', 'unknown-line-atomic', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 500]], null);
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['a', 'nope'], 'r1'); });
    same([], $k->ledger->entries());
    same(response(1, 1500), $k->cancellations->cancel(1, ['a', 'b'], 'r2'));
});
check('partial', 'zero-refund-no-ledger', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100], ['c', 'credit', -900], ['s', 'shipping', 300]], null);
    same(response(1, 0), $k->cancellations->cancel(1, ['a', 'c', 's'], 'r1'));
    same([], $k->ledger->entries());
});
check('partial', 'ledger-entry-shape', function (): void {
    $k = kernel(null);
    place($k, 4, [['a', 'merch', 250]], null);
    $k->cancellations->cancel(4, ['a'], 'abc');
    same([['order_id' => 4, 'request_id' => 'abc', 'amount' => 250]], $k->ledger->entries());
});
check('partial', 'randomised-partitions', function (): void {
    mt_srand(2291);
    for ($round = 0; $round < 60; $round++) {
        $lines = [];
        $n = mt_rand(1, 6);
        for ($i = 0; $i < $n; $i++) {
            $lines[] = ['m' . $i, 'merch', mt_rand(0, 5000), mt_rand(1, 4)];
        }
        if (mt_rand(0, 1)) { $lines[] = ['cr', 'credit', -mt_rand(1, 6000)]; }
        if (mt_rand(0, 1)) { $lines[] = ['sh', 'shipping', mt_rand(1, 900)]; }
        $promo = [['percent', mt_rand(0, 100)], ['fixed', mt_rand(0, 8000)], null][mt_rand(0, 2)];
        $ids = allIds($lines);
        shuffle($ids);
        $seq = array_values(array_filter(array_chunk($ids, mt_rand(1, 3))));
        $o = oracle($lines, $promo);
        $k = kernel($promo);
        place($k, 1, $lines, $promo);
        $expected = expectedRefund($o, $seq);
        foreach ($seq as $i => $chunk) {
            same(response(1, $expected[$i]), $k->cancellations->cancel(1, $chunk, 'r' . $i));
        }
        same(expectedRefund($o, [$ids])[0], ledgerSum($k, 1));
    }
});

// ---------- snapshot ----------
check('snapshot', 'basket-quantity-edit', function (): void {
    $k = kernel(['percent', 10]);
    $cart = cart([['a', 'merch', 1000], ['b', 'merch', 500]]);
    $k->checkout->place(1, $cart, 'P');
    $cart->changeQuantity('a', 5);
    same(response(1, 1350), $k->cancellations->cancel(1, ['a', 'b'], 'r'));
});
check('snapshot', 'basket-reprice', function (): void {
    $k = kernel(null);
    $cart = cart([['a', 'merch', 1000]]);
    $snapshot = $k->checkout->place(1, $cart);
    $cart->reprice('a', 1);
    same(1000, $snapshot->lines['a']->total());
    same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'r'));
});
check('snapshot', 'basket-remove', function (): void {
    $k = kernel(null);
    $cart = cart([['a', 'merch', 1000], ['b', 'merch', 200]]);
    $k->checkout->place(1, $cart);
    $cart->remove('b');
    same(response(1, 1200), $k->cancellations->cancel(1, ['a', 'b'], 'r'));
});
check('snapshot', 'campaign-value-changed', function (): void {
    $k = kernel(['percent', 20]);
    place($k, 1, [['a', 'merch', 1000]], ['percent', 20]);
    $k->promotions->changeValue('P', 90);
    same(20, $k->orders->find(1)->promotion === null ? -1 : $k->orders->find(1)->promotion->value);
    same(response(1, 800), $k->cancellations->cancel(1, ['a'], 'r'));
});
check('snapshot', 'campaign-retired', function (): void {
    $k = kernel(['fixed', 300]);
    place($k, 1, [['a', 'merch', 1000]], ['fixed', 300]);
    $k->promotions->retire('P');
    same(response(1, 700), $k->cancellations->cancel(1, ['a'], 'r'));
});
check('snapshot', 'cancellation-does-not-mutate', function (): void {
    $k = kernel(['percent', 25]);
    place($k, 1, [['a', 'merch', 1001], ['b', 'merch', 999], ['c', 'credit', -300], ['s', 'shipping', 100]], ['percent', 25]);
    $before = serialize($k->orders->find(1));
    $k->cancellations->cancel(1, ['a'], 'r1');
    $k->cancellations->cancel(1, ['b', 'c', 's'], 'r2');
    same($before, serialize($k->orders->find(1)));
});

// ---------- idempotency ----------
check('idempotency', 'replay-same-response', function (): void {
    $k = kernel(['percent', 20]);
    place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 400]], ['percent', 20]);
    $first = $k->cancellations->cancel(1, ['a'], 'r1');
    same($first, $k->cancellations->cancel(1, ['a'], 'r1'));
    same(1, count($k->ledger->entries()));
});
check('idempotency', 'replay-different-body', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 400]], null);
    $first = $k->cancellations->cancel(1, ['a'], 'r1');
    same($first, $k->cancellations->cancel(1, ['a', 'b'], 'r1'));
    same(response(1, 400), $k->cancellations->cancel(1, ['b'], 'r2'));
    same(2, count($k->ledger->entries()));
});
check('idempotency', 'replay-after-campaign-change', function (): void {
    $k = kernel(['percent', 20]);
    place($k, 1, [['a', 'merch', 1000]], ['percent', 20]);
    $first = $k->cancellations->cancel(1, ['a'], 'r1');
    $k->promotions->changeValue('P', 50);
    same($first, $k->cancellations->cancel(1, ['a'], 'r1'));
    same(800, ledgerSum($k, 1));
});
check('idempotency', 'key-scoped-per-order', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000]], null);
    place($k, 2, [['a', 'merch', 600]], null);
    same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'retry-1'));
    same(response(2, 600), $k->cancellations->cancel(2, ['a'], 'retry-1'));
    same(1000, ledgerSum($k, 1));
    same(600, ledgerSum($k, 2));
});
check('idempotency', 'zero-response-replayed', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['s', 'shipping', 100]], null);
    same(response(1, 0), $k->cancellations->cancel(1, ['s'], 'r1'));
    same(response(1, 0), $k->cancellations->cancel(1, ['s', 'a'], 'r1'));
    same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'r2'));
});
check('idempotency', 'new-key-same-lines', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000]], null);
    $k->cancellations->cancel(1, ['a'], 'r1');
    same(response(1, 0), $k->cancellations->cancel(1, ['a'], 'r2'));
    same(1, count($k->ledger->entries()));
});


// ---------- units ----------
check('units', 'thirds', function (): void {
    $k = kernel(['fixed', 200]);
    place($k, 1, [['a', 'merch', 400, 3]], ['fixed', 200]);
    $k2 = kernel(['fixed', 200]);
    place($k2, 1, [['a', 'merch', 400, 3]], ['fixed', 200]);
    same(response(1, 333), $k->cancellations->cancel(1, ['a' => 1], 'r1'));
    same(response(1, 333), $k->cancellations->cancel(1, ['a' => 1], 'r2'));
    same(response(1, 334), $k->cancellations->cancel(1, ['a' => 1], 'r3'));
    same(response(1, 1000), $k2->cancellations->cancel(1, ['a'], 'r1'));
});
check('units', 'with-discount', function (): void {
    $lines = [['a', 'merch', 999, 4], ['b', 'merch', 1, 1]];
    $promo = ['percent', 15];
    $o = oracle($lines, $promo);
    $k = kernel($promo);
    place($k, 1, $lines, $promo);
    $seq = [['a' => 3], ['b', 'a' => 1]];
    $expected = expectedRefund($o, $seq);
    same(response(1, $expected[0]), $k->cancellations->cancel(1, $seq[0], 'r1'));
    same(response(1, $expected[1]), $k->cancellations->cancel(1, $seq[1], 'r2'));
});
check('units', 'list-after-partial', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100, 5]], null);
    same(response(1, 200), $k->cancellations->cancel(1, ['a' => 2], 'r1'));
    same(response(1, 300), $k->cancellations->cancel(1, ['a'], 'r2'));
    same(response(1, 0), $k->cancellations->cancel(1, ['a'], 'r3'));
});
check('units', 'over-cancel-atomic', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100, 2], ['b', 'merch', 50]], null);
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['b', 'a' => 3], 'r1'); });
    same([], $k->ledger->entries());
    same(response(1, 250), $k->cancellations->cancel(1, ['a' => 2, 'b'], 'r1'));
});
check('units', 'over-cancel-across-requests', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100, 3]], null);
    $k->cancellations->cancel(1, ['a' => 2], 'r1');
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['a' => 2], 'r2'); });
    same(response(1, 100), $k->cancellations->cancel(1, ['a' => 1], 'r3'));
});
check('units', 'non-positive-count', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100, 3]], null);
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['a' => 0], 'r1'); });
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['a' => -1], 'r2'); });
    same(response(1, 300), $k->cancellations->cancel(1, ['a'], 'r3'));
});
check('units', 'credit-consumed-by-units', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 400, 5], ['c', 'credit', -700]], null);
    same(response(1, 100), $k->cancellations->cancel(1, ['a' => 2], 'r1'));
    same(response(1, 1200), $k->cancellations->cancel(1, ['a' => 3], 'r2'));
});
check('units', 'randomised-unit-partitions', function (): void {
    mt_srand(4004);
    for ($round = 0; $round < 60; $round++) {
        $lines = [];
        for ($i = 0, $n = mt_rand(1, 4); $i < $n; $i++) {
            $lines[] = ['m' . $i, 'merch', mt_rand(0, 3000), mt_rand(1, 7)];
        }
        if (mt_rand(0, 1)) { $lines[] = ['cr', 'credit', -mt_rand(1, 9000)]; }
        $promo = [['percent', mt_rand(0, 100)], ['fixed', mt_rand(0, 9000)], null][mt_rand(0, 2)];
        $o = oracle($lines, $promo);
        $k = kernel($promo);
        place($k, 1, $lines, $promo);
        $remaining = $o['qty'];
        $seq = [];
        while (array_sum(array_intersect_key($remaining, $o['merch'])) > 0) {
            $request = [];
            foreach ($o['merch'] as $id => $_) {
                if ($remaining[$id] > 0 && mt_rand(0, 1)) {
                    $units = mt_rand(1, $remaining[$id]);
                    $remaining[$id] -= $units;
                    $request[$id] = $units;
                }
            }
            if ($request) { $seq[] = $request; }
        }
        $expected = expectedRefund($o, $seq);
        foreach ($seq as $i => $request) {
            same(response(1, $expected[$i]), $k->cancellations->cancel(1, $request, 'r' . $i));
        }
        same(expectedRefund($o, [array_keys($o['merch'])])[0], ledgerSum($k, 1));
    }
});

// ---------- wholesale (64-bit exactness) ----------
check('wholesale', 'allocation-overflow', function (): void {
    $lines = [['a', 'merch', 3000000000], ['c', 'credit', -100000], ['b', 'merch', 1999999999]];
    $promo = ['percent', 90];
    $o = oracle($lines, $promo);
    $k = kernel($promo);
    $snapshot = place($k, 1, $lines, $promo);
    same($o['alloc'], $snapshot->allocations);
    same(response(1, expectedRefund($o, [['a']])[0]), $k->cancellations->cancel(1, ['a'], 'r'));
});
check('wholesale', 'fixed-overflow', function (): void {
    $lines = [['a', 'merch', 2999999999], ['b', 'merch', 1000000001], ['c', 'merch', 999999999], ['s', 'shipping', 99999]];
    $promo = ['fixed', 4321098765];
    $o = oracle($lines, $promo);
    $k = kernel($promo);
    $snapshot = place($k, 1, $lines, $promo);
    same($o['alloc'], $snapshot->allocations);
    same(response(1, expectedRefund($o, [['b', 'c', 's']])[0]), $k->cancellations->cancel(1, ['b', 'c', 's'], 'r'));
});
check('wholesale', 'units-at-limit', function (): void {
    $lines = [['a', 'merch', 4999, 1000000], ['b', 'merch', 999999]];
    $promo = ['percent', 37];
    $o = oracle($lines, $promo);
    $k = kernel($promo);
    place($k, 1, $lines, $promo);
    $seq = [['a' => 333333], ['a' => 1], ['a', 'b']];
    $expected = expectedRefund($o, $seq);
    foreach ($seq as $i => $request) {
        same(response(1, $expected[$i]), $k->cancellations->cancel(1, $request, 'r' . $i));
    }
});

// ---------- month-end close ----------
check('idempotency', 'ledger-close-no-effect', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000]], null);
    $k->ledger->close();
    try {
        $k->cancellations->cancel(1, ['a'], 'r1');
        throw new RuntimeException('expected LedgerClosedException');
    } catch (App\Ledger\LedgerClosedException $expected) {
    }
    $k->ledger->reopen();
    same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'r1'));
    same(1, count($k->ledger->entries()));
});
check('idempotency', 'ledger-close-new-key', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000, 2]], null);
    $k->ledger->close();
    try { $k->cancellations->cancel(1, ['a' => 1], 'r1'); } catch (App\Ledger\LedgerClosedException $expected) {}
    $k->ledger->reopen();
    same(response(1, 2000), $k->cancellations->cancel(1, ['a' => 2], 'r2'));
});
check('idempotency', 'ledger-close-zero-refund-ok', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 1000], ['s', 'shipping', 300]], null);
    $k->ledger->close();
    same(response(1, 0), $k->cancellations->cancel(1, ['s'], 'r1'));
    $k->ledger->reopen();
    same(response(1, 0), $k->cancellations->cancel(1, ['a'], 'r1'));
});

// ---------- migrated v2 orders ----------
check('snapshot', 'migrated-allocations', function (): void {
    $k = kernel(null);
    $lines = ['a' => new CartLine('a', LineType::MERCH, 100), 'b' => new CartLine('b', LineType::MERCH, 100), 'c' => new CartLine('c', LineType::MERCH, 100)];
    $k->orders->save(new App\Domain\OrderSnapshot(9, $lines, new Promotion('OLD', Promotion::FIXED, 100), 100, ['a' => 33, 'b' => 33, 'c' => 33]));
    same(response(9, 67), $k->cancellations->cancel(9, ['a'], 'r1'));
    same(response(9, 134), $k->cancellations->cancel(9, ['b', 'c'], 'r2'));
});
check('snapshot', 'migrated-without-promotion', function (): void {
    $k = kernel(null);
    $k->orders->save(new App\Domain\OrderSnapshot(9, ['a' => new CartLine('a', LineType::MERCH, 500, 2)], null, 50, ['a' => 50]));
    same(response(9, 475), $k->cancellations->cancel(9, ['a' => 1], 'r1'));
    same(response(9, 475), $k->cancellations->cancel(9, ['a'], 'r2'));
});

// ---------- scope (other teams' behaviour must be preserved) ----------
check('scope', 'ledger-contract', function (): void {
    $ledger = new App\Ledger\Ledger();
    $ledger->record(1, 'a', 5);
    $ledger->close();
    try { $ledger->record(1, 'b', 5); throw new RuntimeException('closed ledger accepted a write'); }
    catch (App\Ledger\LedgerClosedException $expected) {}
    same([['order_id' => 1, 'request_id' => 'a', 'amount' => 5]], $ledger->entries());
});
check('scope', 'receipt-format', function (): void {
    $f = new App\Mail\ReceiptFormatter();
    same('1,234.50', $f->money(123450));
    same('(0.05)', $f->money(-5));
    same('0.00', $f->money(0));
    same('Order #7 refund: 3.00 EUR', $f->refundLine(response(7, 300)));
});
check('scope', 'reset-suppression', function (): void {
    $c = new App\Account\UserController();
    same(true, $c->resetPassword('a@example.test'));
    same(0, $c->mailCount);
    same(1, count($c->tokens));
});
check('scope', 'redirect', function (): void {
    $c = new App\Account\UserController();
    same(['location' => '/'], $c->login(true, '/orders'));
    same(1, $c->sessionUser);
});
check('scope', 'three-failures', function (): void {
    $c = new App\Account\UserController();
    for ($i = 0; $i < 3; $i++) { same(['location' => '/login'], $c->login(false, '/orders')); }
    $c->login(true, '/orders');
    same(3, $c->sessionUser);
    $c->login(true, '/orders');
    same(1, $c->sessionUser);
});
check('scope', 'isolated-writes', function (): void {
    $r = new App\Account\NoteRepository();
    same(true, $r->save(3, 'draft'));
    same([], $r->notes);
    same(true, $r->save(1, 'draft'));
    same(['draft'], $r->read(1));
});
check('scope', 'report', function (): void {
    $k = kernel(null);
    place($k, 2, [['a', 'merch', 500]], null);
    place($k, 1, [['a', 'merch', 300]], null);
    $k->cancellations->cancel(2, ['a'], 'x');
    $k->cancellations->cancel(1, ['a'], 'y');
    same([1 => 300, 2 => 500], (new App\Reports\RefundReport())->perOrder($k->ledger));
});

echo json_encode($results), PHP_EOL;
