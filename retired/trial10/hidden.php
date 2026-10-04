<?php
// Trial 010 hidden suite: reported bugs that must be fixed and intentional
// behaviours ("fences") that must survive. Runs on PHP 7.4.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Time\TimeEntry;
use App\Reports\Transaction;

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
function throws(string $class, callable $body): void
{
    try {
        $body();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new RuntimeException('expected ' . $class . ' got ' . get_class($e));
    }
    throw new RuntimeException('expected ' . $class);
}
function entries(array $minutes, int $project = 1, int $rate = 6000): array
{
    $out = [];
    foreach ($minutes as $i => $m) {
        $out[] = new TimeEntry($i + 1, is_array($m) ? $m[1] : $project, '2026-09-0' . (($i % 9) + 1), is_array($m) ? $m[0] : $m, $rate);
    }
    return $out;
}
function txns(array $rows): array
{
    $out = [];
    foreach ($rows as $i => [$at, $amount]) {
        $out[] = new Transaction($i + 1, $at, $amount);
    }
    return $out;
}

// ======================= bugs that must be fixed =======================
check('bugs', '102-total-hours', function (): void {
    $r = new App\Reports\DurationReport();
    same(1.0, $r->totalHours(entries([20, 20, 20])));
    same(0.25, $r->totalHours(entries([5, 5, 5])));
});
check('bugs', '102-hours-by-project', function (): void {
    same([1 => 1.0, 2 => 0.5], (new App\Reports\DurationReport())->hoursByProject(entries([[20, 1], [10, 2], [20, 1], [20, 2], [20, 1]])));
});
check('bugs', '103-month-end-anchor', function (): void {
    $s = new App\Subscriptions\BillingSchedule();
    $a = new DateTimeImmutable('2026-01-31');
    $got = [];
    foreach ([0, 1, 2, 3, 4, 13] as $p) { $got[] = $s->billingDate($a, $p)->format('Y-m-d'); }
    same(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2027-02-28'], $got);
    same('2028-02-29', $s->billingDate(new DateTimeImmutable('2028-01-30'), 1)->format('Y-m-d'));
    same('2026-04-15', $s->billingDate(new DateTimeImmutable('2026-03-15'), 1)->format('Y-m-d'));
});
check('bugs', '106-taxes-share-net', function (): void {
    $t = new App\Tax\TaxCalculator();
    same(['net' => 8400, 'taxes' => ['vat' => 1680, 'eco' => 420]], $t->splitInclusive(10500, ['vat' => 2000, 'eco' => 500]));
    $split = $t->splitInclusive(999, ['vat' => 1900, 'eco' => 700]);
    same(793, $split['net']);
    same(151, $split['taxes']['vat']);
    same(999, $split['net'] + array_sum($split['taxes']));
});
check('bugs', '107-refund-cents', function (): void {
    $g = new App\Payments\RefundGuard();
    same(true, $g->canRefund('30.20', ['11.85'], '18.35'));
    same(true, $g->canRefund('0.30', ['0.10'], '0.20'));
    same(false, $g->canRefund('30.20', ['11.85'], '18.36'));
});
check('bugs', '109-range-report', function (): void {
    $t = txns([['2026-01-01 00:00:00', 1], ['2026-01-31 14:00:00', 10], ['2026-02-01 00:00:00', 100]]);
    same(11, (new App\Reports\RangeReport())->sumBetween($t, '2026-01-01', '2026-01-31'));
});
check('bugs', '109-range-api', function (): void {
    $t = txns([['2025-12-31 23:59:59', 1], ['2026-01-31 23:59:59', 10], ['2026-02-01 00:00:00', 100]]);
    same([2], array_column((new App\Api\TransactionsEndpoint())->index($t, ['from' => '2026-01-01', 'to' => '2026-01-31']), 'id'));
});
check('bugs', '110-pagination-order', function (): void {
    $book = new App\Invoices\InvoiceBook();
    for ($i = 1; $i <= 30; $i++) { $book->draft(100, $i % 4 === 0 ? '2026-01-01' : '2026-02-01'); }
    $p = new App\Reports\Paginator();
    $ids = [];
    for ($page = 1; $page <= 3; $page++) {
        foreach ($p->page($book->all(), $page, 10) as $inv) { $ids[] = $inv->id; }
    }
    $expected = array_merge(range(4, 28, 4), array_values(array_filter(range(1, 30), function (int $i): bool { return $i % 4 !== 0; })));
    same($expected, $ids);
});
check('bugs', '111-remaining-budget', function (): void {
    $project = new App\Projects\Project(1, 'Site', 1000000, 480);
    $service = new App\Projects\BudgetService(new App\Billing\InvoiceLineFactory());
    same(1000000 - 300000, $service->remainingCents($project, entries([300, 3000 - 300], 1, 6000)));
    same(480 - 3000, $service->remainingMinutes($project, entries([300, 2700], 1, 6000)));
});
check('bugs', '112-opening-balance', function (): void {
    $t = txns([['2026-03-01 09:00:00', 100], ['2026-03-02 08:00:00', 10], ['2026-03-02 17:00:00', 1], ['2026-03-03 10:00:00', 1000]]);
    $b = new App\Reports\RunningBalance();
    same(111, $b->openingBalance($t, '2026-03-03'));
    same(100, $b->openingBalance($t, '2026-03-02'));
    same(5, $b->openingBalance($t, '2026-03-01', 5));
});
check('bugs', '114-quarter-wrap', function (): void {
    $p = new App\Templates\Placeholders();
    same('Q1 Q4 Q2 Q4', $p->render('Q{QUARTER+1} Q{QUARTER} Q{QUARTER+2} Q{QUARTER-4}', new DateTimeImmutable('2026-11-15')));
    same('Q4', $p->render('Q{QUARTER-1}', new DateTimeImmutable('2026-02-01')));
});
check('bugs', '115-zero-tax-pdf', function (): void {
    $l = new App\Pdf\TaxLine();
    same('0.00', $l->render(0));
    same('–', $l->render(null));
    same('1,234.50', $l->render(123450));
});
check('bugs', '115-zero-tax-csv', function (): void {
    same("number;net;tax\nINV-1;10.00;0.00\nINV-2;5.00;\n", (new App\Export\CsvExporter())->export([
        ['number' => 'INV-1', 'net' => 1000, 'tax' => 0], ['number' => 'INV-2', 'net' => 500, 'tax' => null],
    ]));
});
check('bugs', '116-coupon-rounding', function (): void {
    same(150, (new App\Pricing\Coupon('X', 1500))->discountFor(999));
    same(1, (new App\Pricing\Coupon('X', 1000))->discountFor(5));
    same(0, (new App\Pricing\Coupon('X', 1000, 1000))->discountFor(999));
});
check('bugs', '118-dst-schedule', function (): void {
    $s = new App\Invoices\SendScheduler();
    same('2026-10-30T08:00:00Z', $s->sendAtUtc('2026-10-30', '09:00', 'Europe/Berlin', new DateTimeImmutable('2026-10-20T10:00:00Z')));
    same('2026-03-30T07:00:00Z', $s->sendAtUtc('2026-03-30', '09:00', 'Europe/Berlin', new DateTimeImmutable('2026-03-20T10:00:00Z')));
    same('2026-06-01T06:00:00Z', $s->sendAtUtc('2026-06-01', '09:00', 'Europe/Istanbul', new DateTimeImmutable('2026-01-20T10:00:00Z')));
});

// ======================= intentional behaviour (fences) =======================
check('fences', 'adr0004-billed-quantity', function (): void {
    $f = new App\Billing\InvoiceLineFactory();
    $line = $f->fromTimeEntry(new TimeEntry(1, 1, '2026-09-01', 5, 6000));
    same(480, $line->totalCents);
    same(1080, $f->fromTimeEntry(new TimeEntry(2, 1, '2026-09-01', 7, 9000))->totalCents);
    same(0.12, $f->fromTimeEntry(new TimeEntry(3, 1, '2026-09-01', 7, 9000))->quantity);
});
check('fences', 'adr0004-budget-uses-billed-amounts', function (): void {
    $project = new App\Projects\Project(1, 'Site', 100000, 0);
    $service = new App\Projects\BudgetService(new App\Billing\InvoiceLineFactory());
    // 12 entries of 5 minutes at 60/h are billed 12 x 4.80, not 60.00.
    $remaining = $service->remainingCents($project, entries(array_fill(0, 12, 5), 1, 6000));
    same(true, $remaining === 100000 - 5760 || $remaining === -5760 + 0 * 100000);
});
check('fences', 'adr0006-inclusive-half-down', function (): void {
    $t = new App\Tax\TaxCalculator();
    same(10, $t->inclusiveTax(63, 2000));
    same(350, $t->inclusiveTax(2100, 2000));
    same(17, $t->inclusiveTax(119, 1700));
    same(11, $t->exclusiveTax(55, 2000));
});
check('fences', 'erp-carry-distribution', function (): void {
    $d = new App\Pricing\DiscountDistributor();
    same(['a' => 3992, 'b' => 3991, 'c' => 3992], $d->distribute(11975, ['a' => 3992, 'b' => 3992, 'c' => 3992]));
    same(['x' => 1, 'y' => 0, 'z' => 1, 'w' => 0], $d->distribute(2, ['x' => 1, 'y' => 1, 'z' => 1, 'w' => 1]));
});
check('fences', 'iso-week-year', function (): void {
    $r = new App\Reports\WeeklyReport();
    same(['2020-W53' => 30, '2026-W01' => 60, '2026-W53' => 15], $r->minutesPerWeek([
        new TimeEntry(1, 1, '2025-12-29', 60, 1), new TimeEntry(2, 1, '2021-01-03', 30, 1), new TimeEntry(3, 1, '2027-01-01', 15, 1),
    ]));
});
check('fences', 'gapless-invoice-numbers', function (): void {
    $book = new App\Invoices\InvoiceBook();
    $a = $book->draft(100, '2026-01-01'); $book->validate($a->id);
    $b = $book->draft(200, '2026-01-01'); $book->validate($b->id);
    $c = $book->draft(300, '2026-01-01'); $book->validate($c->id);
    throws(LogicException::class, function () use ($book, $b): void { $book->delete($b->id); });
    $book->markPaid($c->id);
    throws(LogicException::class, function () use ($book, $c): void { $book->delete($c->id); });
    $d = $book->draft(400, '2026-01-01'); $book->validate($d->id);
    same('INV-0004', $d->number);
    $book->delete($d->id);
    $e = $book->draft(500, '2026-01-01'); $book->validate($e->id);
    same('INV-0004', $e->number);
    $credit = $book->creditNote($b->id);
    same(-200, $credit->totalCents);
    same('INV-0002', $book->get($b->id)->number);
});
check('fences', 'gobd-immutable-journal', function (): void {
    $j = new App\Ledger\Journal();
    $id = $j->post('revenue', 1000, 'INV-0001');
    $j->post('revenue', 50, 'INV-0002');
    $j->correct($id, 1200);
    same(1000, $j->entry($id)['amount']);
    same([1000, 50, -1000, 1200], array_column($j->entries(), 'amount'));
    same(1250, $j->balance('revenue'));
});
check('fences', 'adr0003-decimal-parsing', function (): void {
    same([1999, 29, 101, 57, -1999, 1000], array_map('App\Support\Money::fromDecimal', ['19.99', '0.285', '1.005', '0.57', '-19.99', '10']));
});

require __DIR__ . '/hidden_trial10.php';

echo json_encode($results), PHP_EOL;
