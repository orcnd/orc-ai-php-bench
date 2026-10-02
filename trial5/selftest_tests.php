<?php
// Exemplary regression tests used by the selftest; must kill every mutant.
declare(strict_types=1);

use App\Domain\Cart;
use App\Domain\CartLine;
use App\Domain\LineType;
use App\Domain\Promotion;
use App\Kernel;
use App\Support\FrozenClock;

/** @param list<array{0:string,1:string,2:int,3?:int}> $lines */
function regressionOrder(Kernel $kernel, int $orderId, array $lines, ?Promotion $promotion = null): Cart
{
    $cart = new Cart();
    foreach ($lines as $line) {
        $cart->add(new CartLine($line[0], $line[1], $line[2], $line[3] ?? 1));
    }
    if ($promotion !== null && $kernel->promotions->find($promotion->code) === null) {
        $kernel->promotions->add($promotion);
    }
    $kernel->checkout->place($orderId, $cart, $promotion === null ? null : $promotion->code);
    return $cart;
}
function lateKernel(string $dir): Kernel
{
    return new Kernel($dir, new FrozenClock('2026-12-01T00:00:00+00:00'));
}
function newDir(): string
{
    return sys_get_temp_dir() . '/selftest-' . getmypid() . '-' . mt_rand();
}

test('percent rounds half up and fixed is capped', function (): void {
    $k = kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 125]], new Promotion('P', Promotion::PERCENT, 2));
    regressionOrder($k, 2, [['a', LineType::MERCH, 700]], new Promotion('F', Promotion::FIXED, 900));
    assertSame(3, $k->orders->find(1)->discount);
    assertSame(700, $k->orders->find(2)->discount);
});
test('largest remainder allocation with ties', function (): void {
    $k = kernel();
    $lines = [['a', LineType::MERCH, 100], ['b', LineType::MERCH, 100], ['c', LineType::MERCH, 100]];
    regressionOrder($k, 1, $lines, new Promotion('F1', Promotion::FIXED, 100));
    regressionOrder($k, 2, $lines, new Promotion('F2', Promotion::FIXED, 200));
    assertSame(['a' => 34, 'b' => 33, 'c' => 33], $k->orders->find(1)->allocations);
    assertSame(['a' => 67, 'b' => 67, 'c' => 66], $k->orders->find(2)->allocations);
});
test('wholesale allocation is exact', function (): void {
    $k = kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 3000000000], ['b', LineType::MERCH, 1999999999]], new Promotion('W', Promotion::PERCENT, 90));
    assertSame(['a' => 2700000000, 'b' => 1799999999], $k->orders->find(1)->allocations);
});
test('returned snapshot is isolated from basket and campaign', function (): void {
    $k = kernel();
    $k->promotions->add(new Promotion('P', Promotion::PERCENT, 10));
    $cart = new Cart();
    $cart->add(new CartLine('a', LineType::MERCH, 1000));
    $snapshot = $k->checkout->place(1, $cart, 'P');
    $cart->reprice('a', 1);
    $k->promotions->changeValue('P', 50);
    assertSame(1000, $snapshot->lines['a']->total());
    assertSame(10, $snapshot->promotion === null ? null : $snapshot->promotion->value);
});
test('credit consumed across partial cancellations', function (): void {
    $dir = newDir();
    $k = new Kernel($dir, new FrozenClock('2026-01-01T00:00:00+00:00'));
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000], ['b', LineType::MERCH, 1000], ['c', LineType::CREDIT, -1500]]);
    assertSame(0, lateKernel($dir)->cancellations->cancel(1, ['a'], 'r1')['refund_cents']);
    assertSame(500, lateKernel($dir)->cancellations->cancel(1, ['b'], 'r2')['refund_cents']);
});
test('unknown line and bad unit counts rejected', function (): void {
    $k = kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 100, 2]]);
    foreach ([['a', 'zzz'], ['a' => 3]] as $i => $request) {
        try {
            $k->cancellations->cancel(1, $request, 'bad' . $i);
            throw new RuntimeException('no exception');
        } catch (InvalidArgumentException $expected) {
        }
    }
    assertSame(100, $k->cancellations->cancel(1, ['a' => 1], 'r2')['refund_cents']);
});
test('unit cancellations floor cumulatively', function (): void {
    $dir = newDir();
    $k = new Kernel($dir, new FrozenClock('2026-01-01T00:00:00+00:00'));
    regressionOrder($k, 1, [['a', LineType::MERCH, 400, 3]], new Promotion('F', Promotion::FIXED, 200));
    assertSame(333, lateKernel($dir)->cancellations->cancel(1, ['a' => 1], 'r1')['refund_cents']);
    assertSame(333, lateKernel($dir)->cancellations->cancel(1, ['a' => 1], 'r2')['refund_cents']);
    assertSame(334, lateKernel($dir)->cancellations->cancel(1, ['a'], 'r3')['refund_cents']);
});
test('zero refunds write no ledger entry or e-mail', function (): void {
    $k = kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 100], ['s', LineType::SHIPPING, 500]]);
    $k->cancellations->cancel(1, ['s'], 'r1');
    assertSame([], $k->ledger->entries());
    assertSame([], $k->outbox->events());
});
test('retries replay after restart', function (): void {
    $dir = newDir();
    $k = new Kernel($dir, new FrozenClock('2026-01-01T00:00:00+00:00'));
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000]]);
    $first = lateKernel($dir)->cancellations->cancel(1, ['a'], 'same');
    assertSame($first, lateKernel($dir)->cancellations->cancel(1, ['a'], 'same'));
    assertSame(1, count($k->ledger->entries()));
});
test('ledger close leaves no trace', function (): void {
    $dir = newDir();
    $k = new Kernel($dir, new FrozenClock('2026-01-01T00:00:00+00:00'));
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000]]);
    $k->ledger->close();
    try {
        lateKernel($dir)->cancellations->cancel(1, ['a'], 'r1');
    } catch (App\Ledger\LedgerClosedException $expected) {
    }
    assertSame([], $k->outbox->events());
    $k->ledger->reopen();
    assertSame(1000, lateKernel($dir)->cancellations->cancel(1, ['a'], 'r2')['refund_cents']);
});
test('migrated order keeps stored allocations', function (): void {
    $k = kernel();
    $lines = ['a' => new CartLine('a', LineType::MERCH, 100), 'b' => new CartLine('b', LineType::MERCH, 100)];
    $k->orders->save(new App\Domain\OrderSnapshot(5, $lines, null, 100, ['a' => 10, 'b' => 90]));
    assertSame(90, $k->cancellations->cancel(5, ['a'], 'r1')['refund_cents']);
});
test('withdrawal refunds delivery with the completing request (Berlin days)', function (): void {
    $cases = [
        // placed, cancelled, expected refunds for [a] then [b]
        ['2026-03-01T23:30:00+00:00', '2026-03-16T22:30:00+00:00', [1000, 590]],
        ['2026-03-01T23:30:00+00:00', '2026-03-16T23:10:00+00:00', [1000, 500]],
    ];
    foreach ($cases as [$placed, $now, $expected]) {
        $clock = new FrozenClock($placed);
        $k = new Kernel(newDir(), $clock);
        regressionOrder($k, 1, [['a', LineType::MERCH, 1000], ['b', LineType::MERCH, 500], ['s', LineType::SHIPPING, 90], ['c', LineType::CREDIT, 0]]);
        $clock->setTo($now);
        assertSame($expected[0], $k->cancellations->cancel(1, ['a'], 'r1')['refund_cents']);
        assertSame($expected[1], $k->cancellations->cancel(1, ['b'], 'r2')['refund_cents']);
    }
});
test('withdrawal nets credit instead of paying it out', function (): void {
    $clock = new FrozenClock('2026-05-04T08:00:00+00:00');
    $k = new Kernel(newDir(), $clock);
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000], ['c', LineType::CREDIT, -1200], ['s', LineType::SHIPPING, 500]]);
    assertSame(300, $k->cancellations->cancel(1, ['a'], 'r1')['refund_cents']);
});
test('concurrent workers refund once', function (): void {
    $dir = newDir();
    $k = new Kernel($dir, new FrozenClock('2026-01-01T00:00:00+00:00'));
    regressionOrder($k, 1, [['a', LineType::MERCH, 100, 6]]);
    $start = microtime(true) + 0.5;
    $procs = [];
    for ($i = 0; $i < 6; $i++) {
        $cmd = implode(' ', array_map('escapeshellarg', [PHP_BINARY, __DIR__ . '/worker.php', $dir,
            '2026-12-01T00:00:00+00:00', sprintf('%.6f', $start), '1', 'k' . ($i % 3), json_encode(['a' => 1])]));
        $pipes = [];
        $procs[] = [proc_open($cmd, [1 => ['pipe', 'w']], $pipes, null, ['STORE_LATENCY_US' => '3000']), $pipes];
    }
    foreach ($procs as [$proc, $pipes]) {
        stream_get_contents($pipes[1]);
        proc_close($proc);
    }
    assertSame(300, array_sum(array_column($k->ledger->entries(), 'amount')));
    assertSame(3, count($k->outbox->events()));
});
