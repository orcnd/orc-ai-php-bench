<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use App\Domain\Cart;
use App\Domain\CartLine;
use App\Domain\LineType;
use App\Domain\Promotion;
use App\Kernel;

$failures = 0;
function test(string $name, callable $body): void
{
    global $failures;
    try {
        $body();
        echo "ok   $name\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL $name: " . $error->getMessage() . "\n";
    }
}
/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(trim($message . ' expected ' . json_encode($expected) . ', got ' . json_encode($actual)));
    }
}

test('receipt formatting', function (): void {
    $formatter = new App\Mail\ReceiptFormatter();
    assertSame('1,234.50', $formatter->money(123450));
    assertSame('(0.05)', $formatter->money(-5));
});

test('order without promotion refunds merchandise', function (): void {
    $kernel = new Kernel();
    $cart = new Cart();
    $cart->add(new CartLine('a', LineType::MERCH, 1200));
    $kernel->checkout->place(1, $cart);
    assertSame(
        ['order_id' => 1, 'refund_cents' => 1200, 'status' => 'refunded'],
        $kernel->cancellations->cancel(1, ['a'], 'k1')
    );
});

test('FIN-2291 promotion with return credit', function (): void {
    $kernel = new Kernel();
    $kernel->promotions->add(new Promotion('SPRING20', Promotion::PERCENT, 20));
    $cart = new Cart();
    $cart->add(new CartLine('shirt', LineType::MERCH, 1000));
    $cart->add(new CartLine('return', LineType::CREDIT, -500));
    $cart->add(new CartLine('delivery', LineType::SHIPPING, 500));
    $kernel->checkout->place(7, $cart, 'SPRING20');
    assertSame(
        ['order_id' => 7, 'refund_cents' => 300, 'status' => 'refunded'],
        $kernel->cancellations->cancel(7, ['shirt', 'return', 'delivery'], 'k1')
    );
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
