<?php
// Exemplary regression tests used by the selftest; must kill every mutant.
declare(strict_types=1);

use App\Domain\Cart;
use App\Domain\CartLine;
use App\Domain\LineType;
use App\Domain\Promotion;
use App\Kernel;

/** @param list<array{0:string,1:string,2:int}> $lines */
function regressionOrder(Kernel $kernel, int $orderId, array $lines, ?Promotion $promotion = null): Cart
{
    $cart = new Cart();
    foreach ($lines as $line) {
        $cart->add(new CartLine($line[0], $line[1], $line[2]));
    }
    if ($promotion !== null && $kernel->promotions->find($promotion->code) === null) {
        $kernel->promotions->add($promotion);
    }
    $kernel->checkout->place($orderId, $cart, $promotion === null ? null : $promotion->code);
    return $cart;
}

test('percent rounds half up', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 125]], new Promotion('P', Promotion::PERCENT, 2));
    assertSame(3, $k->orders->find(1)->discount);
});
test('fixed discount capped at merchandise', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 700]], new Promotion('F', Promotion::FIXED, 900));
    assertSame(700, $k->orders->find(1)->discount);
});
test('largest remainder allocation with ties', function (): void {
    $k = new Kernel();
    $lines = [['a', LineType::MERCH, 100], ['b', LineType::MERCH, 100], ['c', LineType::MERCH, 100]];
    regressionOrder($k, 1, $lines, new Promotion('F1', Promotion::FIXED, 100));
    regressionOrder($k, 2, $lines, new Promotion('F2', Promotion::FIXED, 200));
    assertSame(['a' => 34, 'b' => 33, 'c' => 33], $k->orders->find(1)->allocations);
    assertSame(['a' => 67, 'b' => 67, 'c' => 66], $k->orders->find(2)->allocations);
});
test('snapshot survives basket and campaign edits', function (): void {
    $k = new Kernel();
    $cart = regressionOrder($k, 1, [['a', LineType::MERCH, 1000]], new Promotion('P', Promotion::PERCENT, 10));
    $cart->changeQuantity('a', 3);
    $k->promotions->changeValue('P', 50);
    $snapshot = $k->orders->find(1);
    assertSame(1000, $snapshot->lines['a']->total());
    assertSame(10, $snapshot->promotion === null ? null : $snapshot->promotion->value);
    assertSame(900, $k->cancellations->cancel(1, ['a'], 'r')['refund_cents']);
});
test('credit consumed by partial cancellation', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000], ['b', LineType::MERCH, 1000], ['c', LineType::CREDIT, -1500]]);
    assertSame(0, $k->cancellations->cancel(1, ['a'], 'r1')['refund_cents']);
    assertSame(500, $k->cancellations->cancel(1, ['b'], 'r2')['refund_cents']);
});
test('unknown line rejected atomically', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000]]);
    try {
        $k->cancellations->cancel(1, ['a', 'zzz'], 'r1');
        throw new RuntimeException('no exception');
    } catch (InvalidArgumentException $expected) {
    }
    assertSame(1000, $k->cancellations->cancel(1, ['a'], 'r2')['refund_cents']);
});
test('zero refund writes no ledger entry', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 100], ['s', LineType::SHIPPING, 500]]);
    $k->cancellations->cancel(1, ['s'], 'r1');
    assertSame([], $k->ledger->entries());
});
test('retries replay per order', function (): void {
    $k = new Kernel();
    regressionOrder($k, 1, [['a', LineType::MERCH, 1000]]);
    regressionOrder($k, 2, [['a', LineType::MERCH, 600]]);
    $first = $k->cancellations->cancel(1, ['a'], 'same');
    assertSame($first, $k->cancellations->cancel(1, ['a'], 'same'));
    assertSame(600, $k->cancellations->cancel(2, ['a'], 'same')['refund_cents']);
    assertSame(2, count($k->ledger->entries()));
});
