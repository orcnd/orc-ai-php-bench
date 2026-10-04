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
test('happy path in order', function (): void {
    $p = new App\Payments\OrderProjector();
    foreach ([
        ['id' => 'e1', 'type' => 'OrderCreated', 'order' => 'o1', 'at' => '2026-09-01T10:00:00Z', 'total' => 100],
        ['id' => 'e2', 'type' => 'PaymentAuthorized', 'order' => 'o1', 'at' => '2026-09-01T10:00:05Z', 'auth' => 'a1', 'amount' => 100],
        ['id' => 'e3', 'type' => 'PaymentCaptured', 'order' => 'o1', 'at' => '2026-09-01T10:05:00Z', 'auth' => 'a1', 'capture' => 'c1', 'amount' => 100],
    ] as $event) {
        $p->apply($event);
    }
    assertSame(['status' => 'captured', 'total' => 100, 'captured' => 100, 'refunded' => 0], $p->state('o1'));
    assertSame([], $p->commands());
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
