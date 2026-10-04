<?php
// Trial 011 hidden suite: V1 (frozen legacy code) and V2 (candidate) share a store and a queue.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Customers\Customer;
use App\Customers\CustomerRepository;
use App\Storage\FileStore;
use Legacy\V1\CustomerRecord as V1;
use Legacy\V1\InvoiceQueue as V1Queue;

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
function store(): FileStore
{
    static $n = 0;
    $base = sys_get_temp_dir() . '/orc11-' . getmypid();
    if ($n === 0) {
        register_shutdown_function(function () use ($base): void { exec('rm -rf ' . escapeshellarg($base)); });
    }
    return new FileStore($base . '/' . (++$n));
}
function v2(FileStore $s): CustomerRepository
{
    return new CustomerRepository($s);
}
function fields(Customer $c): array
{
    return [$c->name, $c->street, $c->postcode, $c->city, $c->feeCents, $c->currency];
}

// ---------- V2 reads V1 data ----------
check('compat', 'v2-reads-v1', function (): void {
    $s = store();
    V1::save($s, 1, 'Ana', 'Hauptstraße 5, 10115 Berlin', 19.99);
    V1::save($s, 2, 'Bo', 'c/o Müller, Am Markt 1, 80331 München', 0.29);
    V1::save($s, 3, 'Cy', 'Seestrasse 9, 8002 Zürich', 1.15);
    same(['Ana', 'Hauptstraße 5', '10115', 'Berlin', 1999, 'EUR'], fields(v2($s)->find(1)));
    same(['Bo', 'c/o Müller, Am Markt 1', '80331', 'München', 29, 'EUR'], fields(v2($s)->find(2)));
    same(['Cy', 'Seestrasse 9', '8002', 'Zürich', 115, 'EUR'], fields(v2($s)->find(3)));
});
check('compat', 'v1-reads-v2', function (): void {
    $s = store();
    v2($s)->save(new Customer(4, 'Di', 'Hauptstraße 5', '10115', 'Berlin', 2550, 'USD'));
    same(['name' => 'Di', 'address' => 'Hauptstraße 5, 10115 Berlin', 'monthly_fee' => 25.5], V1::load($s, 4));
});
check('compat', 'v1-rewrite-keeps-v2-data', function (): void {
    $s = store();
    v2($s)->save(new Customer(5, 'Ed', 'Hauptstraße 5', '10115', 'Berlin', 4200, 'CHF'));
    V1::changeAddress($s, 5, 'Ringstraße 1, 1010 Wien');
    same(['Ed', 'Ringstraße 1', '1010', 'Wien', 4200, 'CHF'], fields(v2($s)->find(5)));
});
check('compat', 'v2-roundtrip-after-v1-change', function (): void {
    $s = store();
    v2($s)->save(new Customer(6, 'Fa', 'Alte Gasse 2', '50667', 'Köln', 990, 'USD'));
    V1::changeAddress($s, 6, 'Neue Gasse 3, 50668 Köln');
    $c = v2($s)->find(6);
    $c->feeCents = 1090;
    v2($s)->save($c);
    same(['Fa', 'Neue Gasse 3', '50668', 'Köln', 1090, 'USD'], fields(v2($s)->find(6)));
    same(10.9, V1::load($s, 6)['monthly_fee']);
});

// ---------- messages in both directions ----------
check('messages', 'v1-message-to-v2-worker', function (): void {
    $s = store();
    v2($s)->save(new Customer(7, 'Gu', 'Weg 1', '10115', 'Berlin', 1999, 'USD'));
    (new App\Billing\InvoiceWorker($s))->handle(V1Queue::request($s, 7, 'm-1'));
    same(['customer' => 7, 'amount' => 1999, 'currency' => 'USD'], (new App\Billing\Invoices($s))->get('m-1'));
});
check('messages', 'v2-message-to-v1-worker', function (): void {
    $s = store();
    v2($s)->save(new Customer(8, 'Ha', 'Weg 2', '10115', 'Berlin', 129, 'CHF'));
    V1Queue::handle($s, (new App\Billing\InvoiceRequester($s))->request(8, 'm-2'));
    same(['customer' => 8, 'amount' => 129, 'currency' => 'CHF'], (new App\Billing\Invoices($s))->get('m-2'));
});
check('messages', 'v2-message-to-v2-worker', function (): void {
    $s = store();
    v2($s)->save(new Customer(9, 'Io', 'Weg 3', '10115', 'Berlin', 115, 'EUR'));
    (new App\Billing\InvoiceWorker($s))->handle((new App\Billing\InvoiceRequester($s))->request(9, 'm-3'));
    same(['customer' => 9, 'amount' => 115, 'currency' => 'EUR'], (new App\Billing\Invoices($s))->get('m-3'));
});
check('messages', 'redelivery-is-harmless', function (): void {
    $s = store();
    v2($s)->save(new Customer(10, 'Jo', 'Weg 4', '10115', 'Berlin', 500, 'USD'));
    $message = V1Queue::request($s, 10, 'm-4');
    $worker = new App\Billing\InvoiceWorker($s);
    $worker->handle($message);
    $c = v2($s)->find(10);
    $c->currency = 'CHF';
    v2($s)->save($c);
    $worker->handle($message);
    same(['customer' => 10, 'amount' => 500, 'currency' => 'USD'], (new App\Billing\Invoices($s))->get('m-4'));
});

// ---------- migration ----------
function legacyFleet(FileStore $s, int $n = 30): array
{
    $expected = [];
    $fees = [19.99, 0.29, 1.15, 4.35, 0.57, 100.0, 7.0];
    for ($i = 1; $i <= $n; $i++) {
        $fee = $fees[$i % count($fees)];
        $address = $i % 5 === 0 ? "c/o Firma $i, Hof $i, 1" . sprintf('%04d', $i) . " Ort$i" : "Straße $i, 2" . sprintf('%04d', $i) . " Stadt$i";
        V1::save($s, $i, "Kunde $i", $address, $fee);
        $expected[$i] = ['Kunde ' . $i, $address, (int) round($fee * 100)];
    }
    return $expected;
}
function assertFleet(FileStore $s, array $expected): void
{
    foreach ($expected as $id => [$name, $address, $cents]) {
        $c = v2($s)->find($id);
        same([$name, $address, $cents], [$c->name, $c->street . ', ' . trim($c->postcode . ' ' . $c->city), $c->feeCents]);
        same($address, V1::load($s, $id)['address']);
    }
}
check('migration', 'migrates-everything-once', function (): void {
    $s = store();
    $expected = legacyFleet($s);
    $m = new App\Migration\CustomerMigration($s);
    $m->run();
    assertFleet($s, $expected);
    same(0, $m->run());
    assertFleet($s, $expected);
});
check('migration', 'killed-and-restarted', function (): void {
    global $argv;
    foreach ([1, 7, 18, 29] as $crashAt) {
        $s = store();
        $expected = legacyFleet($s);
        $prop = new ReflectionProperty(FileStore::class, 'directory');
        $prop->setAccessible(true);
        $dir = (string) $prop->getValue($s);
        $cmd = 'STORE_CRASH_AT=' . $crashAt . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/hidden_migrate.php')
            . ' ' . escapeshellarg($argv[1]) . ' ' . escapeshellarg($dir) . ' 2>&1';
        exec($cmd, $out, $code);
        (new App\Migration\CustomerMigration($s))->run();
        assertFleet($s, $expected);
        same(0, (new App\Migration\CustomerMigration($s))->run());
    }
});
check('migration', 'v1-writes-during-migration', function (): void {
    $s = store();
    $expected = legacyFleet($s, 12);
    (new App\Migration\CustomerMigration($s))->run();
    V1::changeAddress($s, 3, 'Umzugweg 1, 99999 Neustadt');
    $expected[3][1] = 'Umzugweg 1, 99999 Neustadt';
    assertFleet($s, $expected);
    (new App\Migration\CustomerMigration($s))->run();
    assertFleet($s, $expected);
});
check('migration', 'currency-survives-migration', function (): void {
    $s = store();
    legacyFleet($s, 3);
    $c = v2($s)->find(2);
    $c->currency = 'USD';
    v2($s)->save($c);
    (new App\Migration\CustomerMigration($s))->run();
    V1::changeAddress($s, 2, 'Neu 1, 12345 Ort');
    (new App\Migration\CustomerMigration($s))->run();
    same('USD', v2($s)->find(2)->currency);
});

// ---------- lossless round-trips ----------
check('lossless', 'unchanged-save-keeps-v1-bytes', function (): void {
    foreach (['10 Downing Street, London SW1A 2AA', 'Postfach 12 34, 10115  Berlin', 'Am Markt 1,10115 Berlin',
              'Hauptstraße 5, 10115 Berlin', 'Rue de Rivoli 1, 75001 Paris, France'] as $i => $address) {
        $s = store();
        V1::save($s, $i + 1, 'K', $address, 12.5);
        $c = v2($s)->find($i + 1);
        v2($s)->save($c);
        same($address, V1::load($s, $i + 1)['address']);
        same(12.5, V1::load($s, $i + 1)['monthly_fee']);
    }
});
check('lossless', 'fee-only-change-keeps-address', function (): void {
    $s = store();
    V1::save($s, 1, 'K', '10 Downing Street, London SW1A 2AA', 12.5);
    $c = v2($s)->find(1);
    $c->feeCents = 1999;
    $c->currency = 'GBP';
    v2($s)->save($c);
    same('10 Downing Street, London SW1A 2AA', V1::load($s, 1)['address']);
    same(19.99, V1::load($s, 1)['monthly_fee']);
    same('GBP', v2($s)->find(1)->currency);
});
check('lossless', 'migration-keeps-v1-bytes', function (): void {
    $s = store();
    V1::save($s, 1, 'K', 'Am Markt 1,10115 Berlin', 0.29);
    V1::save($s, 2, 'L', 'Postfach 12 34, 10115  Berlin', 1.15);
    (new App\Migration\CustomerMigration($s))->run();
    same('Am Markt 1,10115 Berlin', V1::load($s, 1)['address']);
    same('Postfach 12 34, 10115  Berlin', V1::load($s, 2)['address']);
    same(115, v2($s)->find(2)->feeCents);
});

echo json_encode($results), PHP_EOL;
