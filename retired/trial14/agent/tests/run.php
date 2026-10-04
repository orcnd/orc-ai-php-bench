<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use App\Money\Allocation;

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

test('allocate splits a refund between two payment methods', function (): void {
    assertSame(['card' => 600, 'voucher' => 400], Allocation::allocate(1000, ['card' => 3, 'voucher' => 2]));
});
test('prorate half a month', function (): void {
    assertSame(1500, Allocation::prorate(3000, '2026-04-01', '2026-04-16', '2026-04-01', '2026-05-01'));
});
test('installments', function (): void {
    assertSame([334, 333, 333], Allocation::installments(1000, 3));
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
