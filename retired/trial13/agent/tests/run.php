<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

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
        throw new RuntimeException(trim($message . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}
$fixtures = require __DIR__ . '/fixtures/orders.php';
$repository = new App\Orders\OrderRepository($fixtures['shop'], $fixtures['legacy']);

test('shop order full cancellation', function () use ($repository): void {
    assertSame(['order' => 'S-10001', 'refund_cents' => 500 + 300 + 490], (new App\Refunds\CancellationService($repository))->cancel('S-10001'));
});
test('shop order partial cancellation', function () use ($repository): void {
    assertSame(250, (new App\Refunds\CancellationService($repository))->cancel('S-10001', ['BOX-S' => 2])['refund_cents']);
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
