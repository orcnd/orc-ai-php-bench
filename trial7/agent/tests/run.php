<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

use App\Billing\InvoiceLineFactory;
use App\Ledger\Journal;
use App\Reports\DurationReport;
use App\Support\Money;
use App\Time\TimeEntry;

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

test('decimal amounts parse to cents', function (): void {
    assertSame(1999, Money::fromDecimal('19.99'));
    assertSame(-500, Money::fromDecimal('-5'));
});

test('short time entries are billed per ADR 0004', function (): void {
    $line = (new InvoiceLineFactory())->fromTimeEntry(new TimeEntry(1, 1, '2026-09-01', 5, 6000));
    assertSame(0.08, $line->quantity);
    assertSame(480, $line->totalCents);
});

test('dashboard total hours', function (): void {
    $entries = [];
    for ($i = 1; $i <= 3; $i++) {
        $entries[] = new TimeEntry($i, 1, '2026-09-01', 20, 6000);
    }
    assertSame(0.99, (new DurationReport())->totalHours($entries));
});

test('journal corrections keep the audit trail', function (): void {
    $journal = new Journal();
    $id = $journal->post('revenue', 1000, 'Invoice INV-0001');
    $journal->correct($id, 1200);
    assertSame(3, count($journal->entries()));
    assertSame(1200, $journal->balance('revenue'));
});

echo $failures === 0 ? "All tests passed\n" : "$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
