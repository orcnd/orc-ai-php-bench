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
function store(): App\Storage\FileStore
{
    return new App\Storage\FileStore(sys_get_temp_dir() . '/bill512-' . getmypid() . '-' . mt_rand());
}

test('V1 customers can be saved and loaded', function (): void {
    $store = store();
    Legacy\V1\CustomerRecord::save($store, 1, 'Ana', 'Hauptstraße 5, 10115 Berlin', 19.99);
    assertSame(19.99, Legacy\V1\CustomerRecord::load($store, 1)['monthly_fee']);
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
