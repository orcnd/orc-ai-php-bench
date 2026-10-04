<?php
// Exemplary regression tests used by the selftest; must kill every mutant.
declare(strict_types=1);

use App\Reports\Transaction;
use App\Time\TimeEntry;

/** @return list<TimeEntry> */
function minutesEntries(array $minutes): array
{
    $out = [];
    foreach ($minutes as $i => $m) {
        $out[] = new TimeEntry($i + 1, $i % 2 + 1, '2026-09-01', $m, 6000);
    }
    return $out;
}
test('#102 hours sum minutes', function (): void {
    assertSame(1.0, (new App\Reports\DurationReport())->totalHours(minutesEntries([20, 20, 20])));
    assertSame([1 => 0.67, 2 => 0.33], (new App\Reports\DurationReport())->hoursByProject(minutesEntries([20, 20, 20])));
});
test('#103 month-end anchored schedule', function (): void {
    $s = new App\Subscriptions\BillingSchedule();
    assertSame('2026-02-28', $s->billingDate(new DateTimeImmutable('2026-01-31'), 1)->format('Y-m-d'));
    assertSame('2026-03-31', $s->billingDate(new DateTimeImmutable('2026-01-31'), 2)->format('Y-m-d'));
});
test('#106 taxes share the net', function (): void {
    assertSame(['net' => 8400, 'taxes' => ['vat' => 1680, 'eco' => 420]], (new App\Tax\TaxCalculator())->splitInclusive(10500, ['vat' => 2000, 'eco' => 500]));
});
test('#107 refunds in cents', function (): void {
    assertSame(true, (new App\Payments\RefundGuard())->canRefund('30.20', ['11.85'], '18.35'));
});
test('#109 inclusive ranges', function (): void {
    $t = [new Transaction(1, '2026-01-31 14:00:00', 10)];
    assertSame(10, (new App\Reports\RangeReport())->sumBetween($t, '2026-01-01', '2026-01-31'));
    assertSame(1, count((new App\Api\TransactionsEndpoint())->index($t, ['from' => '2026-01-01', 'to' => '2026-01-31'])));
});
test('#110 pages ordered by due date then id', function (): void {
    $book = new App\Invoices\InvoiceBook();
    for ($i = 1; $i <= 20; $i++) {
        $book->draft(1, '2026-01-01');
    }
    $ids = array_map(function (App\Invoices\Invoice $i): int { return $i->id; }, (new App\Reports\Paginator())->page($book->all(), 1, 20));
    assertSame(range(1, 20), $ids);
});
test('#111 #112 #114 #116', function (): void {
    $project = new App\Projects\Project(1, 'P', 100000, 60);
    assertSame(100000 - 6000, (new App\Projects\BudgetService(new App\Billing\InvoiceLineFactory()))->remainingCents($project, [new TimeEntry(1, 1, '2026-09-01', 60, 6000)]));
    $t = [new Transaction(1, '2026-03-02 08:00:00', 10), new Transaction(2, '2026-03-02 17:00:00', 1)];
    assertSame(11, (new App\Reports\RunningBalance())->openingBalance($t, '2026-03-03'));
    assertSame('1', (new App\Templates\Placeholders())->render('{QUARTER+1}', new DateTimeImmutable('2026-11-01')));
    assertSame(150, (new App\Pricing\Coupon('X', 1500))->discountFor(999));
});
test('#115 zero tax printed', function (): void {
    assertSame('0.00', (new App\Pdf\TaxLine())->render(0));
    assertSame("number;net;tax\nA;1.00;0.00\n", (new App\Export\CsvExporter())->export([['number' => 'A', 'net' => 100, 'tax' => 0]]));
});
test('#118 schedule uses the target day offset', function (): void {
    assertSame('2026-10-30T08:00:00Z', (new App\Invoices\SendScheduler())->sendAtUtc('2026-10-30', '09:00', 'Europe/Berlin', new DateTimeImmutable('2026-10-20T10:00:00Z')));
});
test('fences stay intact', function (): void {
    assertSame(10, (new App\Tax\TaxCalculator())->inclusiveTax(63, 2000));
    assertSame(['a' => 3992, 'b' => 3991, 'c' => 3992], (new App\Pricing\DiscountDistributor())->distribute(11975, ['a' => 3992, 'b' => 3992, 'c' => 3992]));
    assertSame(['2026-W01' => 60], (new App\Reports\WeeklyReport())->minutesPerWeek([new TimeEntry(1, 1, '2025-12-29', 60, 1)]));
    $book = new App\Invoices\InvoiceBook();
    $a = $book->draft(1, '2026-01-01');
    $book->validate($a->id);
    $b = $book->draft(1, '2026-01-01');
    $book->validate($b->id);
    try {
        $book->delete($a->id);
        throw new RuntimeException('deleted a non-last invoice');
    } catch (LogicException $expected) {
    }
    $j = new App\Ledger\Journal();
    $id = $j->post('r', 1000, 'x');
    $j->correct($id, 1200);
    assertSame([1000, -1000, 1200], array_column($j->entries(), 'amount'));
});
test('time entries billed as rounded hours x rate', function (): void {
    assertSame(480, (new App\Billing\InvoiceLineFactory())->fromTimeEntry(new TimeEntry(1, 1, '2026-09-01', 5, 6000))->totalCents);
});
