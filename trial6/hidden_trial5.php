<?php
// Trial 005 additions: persistence across requests, real multi-process
// concurrency, statutory withdrawal (ADR 0011) and outbox exactly-once.
declare(strict_types=1);

use App\Domain\CartLine;
use App\Domain\LineType;
use App\Kernel;

/** @param list<array{0:string,1:array}> $jobs [key, lines] @return list<array> */
function runWorkers(string $dir, int $orderId, array $jobs, string $now): array
{
    global $argv;
    $start = microtime(true) + 0.6;
    $env = array_merge(getenv(), ['STORE_LATENCY_US' => '3000', 'XDEBUG_MODE' => 'off']);
    $procs = [];
    foreach ($jobs as [$key, $lines]) {
        $cmd = [PHP_BINARY, __DIR__ . '/hidden_worker.php', $argv[1], $dir, $now, sprintf('%.6f', $start),
                (string) $orderId, $key, json_encode($lines)];
        $cmd = implode(' ', array_map('escapeshellarg', $cmd));
        $pipes = [];
        $procs[] = [proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env), $pipes];
    }
    $out = [];
    foreach ($procs as [$proc, $pipes]) {
        $text = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $decoded = json_decode((string) $text, true);
        $out[] = is_array($decoded) ? $decoded : ['error' => 'crash', 'message' => substr((string) $text, 0, 200)];
    }
    return $out;
}
function events(Kernel $k): array
{
    return array_values(array_filter($k->outbox->events(), function (array $e): bool { return $e['type'] === 'refund.issued'; }));
}

// ---------- persistence: every request is a fresh process ----------
check('persistence', 'replay-after-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 1000], ['b', 'merch', 300]], null);
    $first = $k1->cancellations->cancel(1, ['a'], 'r1');
    $k2 = kernelAt($dir, LATER);
    same($first, $k2->cancellations->cancel(1, ['a', 'b'], 'r1'));
    same(1, count($k2->ledger->entries()));
});
check('persistence', 'units-survive-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 100, 5]], null);
    same(response(1, 200), $k1->cancellations->cancel(1, ['a' => 2], 'r1'));
    $k2 = kernelAt($dir, LATER);
    same(response(1, 300), $k2->cancellations->cancel(1, ['a'], 'r2'));
    $k3 = kernelAt($dir, LATER);
    same(response(1, 0), $k3->cancellations->cancel(1, ['a'], 'r3'));
});
check('persistence', 'credit-netting-survives-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 1000], ['b', 'merch', 1000], ['c', 'credit', -1500]], null);
    same(response(1, 0), $k1->cancellations->cancel(1, ['a'], 'r1'));
    same(response(1, 500), kernelAt($dir, LATER)->cancellations->cancel(1, ['b'], 'r2'));
});
check('persistence', 'over-cancel-across-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 100, 3]], null);
    $k1->cancellations->cancel(1, ['a' => 2], 'r1');
    $k2 = kernelAt($dir, LATER);
    throwsInvalid(function () use ($k2): void { $k2->cancellations->cancel(1, ['a' => 2], 'r2'); });
    same(response(1, 100), $k2->cancellations->cancel(1, ['a' => 1], 'r3'));
});
check('persistence', 'ledger-close-retry-after-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 1000]], null);
    $k1->ledger->close();
    try { $k1->cancellations->cancel(1, ['a'], 'r1'); } catch (App\Ledger\LedgerClosedException $expected) {}
    $k2 = kernelAt($dir, LATER);
    $k2->ledger->reopen();
    same(response(1, 1000), $k2->cancellations->cancel(1, ['a'], 'r1'));
    same(1, count($k2->ledger->entries()));
});
check('persistence', 'keys-per-order-after-restart', function (): void {
    $dir = storeDir();
    $k1 = kernel(null, $dir);
    place($k1, 1, [['a', 'merch', 1000]], null);
    place($k1, 2, [['a', 'merch', 600]], null);
    $k1->cancellations->cancel(1, ['a'], 'k');
    same(response(2, 600), kernelAt($dir, LATER)->cancellations->cancel(2, ['a'], 'k'));
});

// ---------- concurrency: simultaneous PHP-FPM workers ----------
check('concurrency', 'same-key-burst', function (): void {
    for ($round = 0; $round < 2; $round++) {
        $dir = storeDir();
        $k = kernel(null, $dir);
        place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 250]], null);
        $out = runWorkers($dir, 1, array_fill(0, 6, ['dup', ['a']]), LATER);
        foreach ($out as $response) { same(response(1, 1000), $response); }
        same(1, count($k->ledger->entries()));
        same(1000, ledgerSum($k, 1));
    }
});
check('concurrency', 'different-keys-same-lines', function (): void {
    for ($round = 0; $round < 2; $round++) {
        $dir = storeDir();
        $k = kernel(null, $dir);
        place($k, 1, [['a', 'merch', 1000], ['b', 'merch', 250]], null);
        $jobs = [];
        for ($i = 0; $i < 6; $i++) { $jobs[] = ['k' . $i, ['a']]; }
        $out = runWorkers($dir, 1, $jobs, LATER);
        same(1000, array_sum(array_map(function (array $r): int { return $r['refund_cents'] ?? -999999; }, $out)));
        same(1000, ledgerSum($k, 1));
        same(1, count($k->ledger->entries()));
    }
});
check('concurrency', 'unit-cancellations-race', function (): void {
    for ($round = 0; $round < 2; $round++) {
        $dir = storeDir();
        $k = kernel(['fixed', 100], $dir);
        place($k, 1, [['a', 'merch', 400, 6], ['c', 'credit', -300]], ['fixed', 100]);
        $jobs = [];
        for ($i = 0; $i < 6; $i++) { $jobs[] = ['u' . $i, ['a' => 1]]; }
        $out = runWorkers($dir, 1, $jobs, LATER);
        same(2000, array_sum(array_map(function (array $r): int { return $r['refund_cents'] ?? -999999; }, $out)));
        same(2000, ledgerSum($k, 1));
    }
});
check('concurrency', 'emails-match-ledger-under-load', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 100, 4]], null);
    $jobs = [['x', ['a' => 1]], ['x', ['a' => 1]], ['y', ['a' => 1]], ['y', ['a' => 1]], ['z', ['a' => 2]], ['w', ['a']]];
    runWorkers($dir, 1, $jobs, LATER);
    same(400, ledgerSum($k, 1));
    same(count($k->ledger->entries()), count(events($k)));
});
check('concurrency', 'independent-orders-parallel', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 700]], null);
    place($k, 2, [['a', 'merch', 300]], null);
    $out1 = runWorkers($dir, 1, [['k', ['a']], ['k', ['a']], ['k', ['a']]], LATER);
    $out2 = runWorkers($dir, 2, [['k', ['a']], ['k', ['a']], ['k', ['a']]], LATER);
    same(response(1, 700), $out1[0]);
    same(response(2, 300), $out2[2]);
    same(1000, array_sum(array_column($k->ledger->entries(), 'amount')));
});

// ---------- statutory withdrawal (ADR 0011) ----------
/** Full cancellation of $lines placed at $placed, requested at $now. */
function withdrawal(array $lines, string $placed, array $sequence): array
{
    $k = kernel(null);
    clockOf($k)->setTo($placed);
    $k->checkout->place(1, cart($lines));
    $out = [];
    foreach ($sequence as $i => [$now, $request]) {
        clockOf($k)->setTo($now);
        $out[] = $k->cancellations->cancel(1, $request, 'w' . $i);
    }
    return $out;
}
$order = [['a', 'merch', 1000], ['b', 'merch', 500], ['s', 'shipping', 490]];
check('withdrawal', 'within-period-full', function () use ($order): void {
    same([response(1, 1990)], withdrawal($order, '2026-05-04T08:00:00+00:00', [['2026-05-10T12:00:00+00:00', ['a', 'b']]]));
});
check('withdrawal', 'after-period-full', function () use ($order): void {
    same([response(1, 1500)], withdrawal($order, '2026-05-04T08:00:00+00:00', [['2026-05-19T12:00:00+00:00', ['a', 'b', 's']]]));
});
check('withdrawal', 'berlin-day-boundary', function () use ($order): void {
    // Placed 1 Mar 23:30 UTC = 2 Mar 00:30 Berlin: period ends 16 Mar 23:59:59 CET (22:59:59 UTC).
    same([response(1, 1990)], withdrawal($order, '2026-03-01T23:30:00+00:00', [['2026-03-16T22:30:00+00:00', ['a', 'b']]]));
    same([response(1, 1500)], withdrawal($order, '2026-03-01T23:30:00+00:00', [['2026-03-16T23:10:00+00:00', ['a', 'b']]]));
});
check('withdrawal', 'dst-boundary', function () use ($order): void {
    // Placed 20 Mar (CET); period ends 3 Apr 23:59:59 CEST = 21:59:59 UTC.
    same([response(1, 1500)], withdrawal($order, '2026-03-20T12:00:00+00:00', [['2026-04-03T22:30:00+00:00', ['a', 'b']]]));
    same([response(1, 1990)], withdrawal($order, '2026-03-20T12:00:00+00:00', [['2026-04-03T21:30:00+00:00', ['a', 'b']]]));
});
check('withdrawal', 'completing-request-gets-delivery', function () use ($order): void {
    same([response(1, 1000), response(1, 990)], withdrawal($order, '2026-05-04T08:00:00+00:00', [
        ['2026-05-05T08:00:00+00:00', ['a']], ['2026-05-06T08:00:00+00:00', ['b']],
    ]));
});
check('withdrawal', 'partial-never-gets-delivery', function () use ($order): void {
    same([response(1, 1000), response(1, 0)], withdrawal($order, '2026-05-04T08:00:00+00:00', [
        ['2026-05-05T08:00:00+00:00', ['a', 's']], ['2026-05-06T08:00:00+00:00', ['s']],
    ]));
});
check('withdrawal', 'completed-after-period', function () use ($order): void {
    same([response(1, 1000), response(1, 500)], withdrawal($order, '2026-05-04T08:00:00+00:00', [
        ['2026-05-05T08:00:00+00:00', ['a']], ['2026-05-30T08:00:00+00:00', ['b', 's']],
    ]));
});
check('withdrawal', 'credit-netted-not-paid-out', function (): void {
    $lines = [['a', 'merch', 1000], ['c', 'credit', -1200], ['s', 'shipping', 500]];
    same([response(1, 300)], withdrawal($lines, '2026-05-04T08:00:00+00:00', [['2026-05-05T08:00:00+00:00', ['a']]]));
});
check('withdrawal', 'units-complete-order', function (): void {
    $lines = [['a', 'merch', 300, 3], ['s', 'shipping', 100]];
    same([response(1, 600), response(1, 400)], withdrawal($lines, '2026-05-04T08:00:00+00:00', [
        ['2026-05-05T08:00:00+00:00', ['a' => 2]], ['2026-05-06T08:00:00+00:00', ['a' => 1]],
    ]));
});
check('withdrawal', 'migrated-order-old', function (): void {
    $k = kernelAt(storeDir(), '2026-05-05T08:00:00+00:00');
    $k->orders->save(new App\Domain\OrderSnapshot(9, ['a' => new CartLine('a', LineType::MERCH, 500), 's' => new CartLine('s', LineType::SHIPPING, 400)], null, 0, ['a' => 0], '2026-01-02T10:00:00+00:00'));
    same(response(9, 500), $k->cancellations->cancel(9, ['a', 's'], 'r'));
});

// ---------- outbox: one e-mail per ledger entry ----------
check('outbox', 'one-event-per-refund', function (): void {
    $k = kernel(null);
    place($k, 3, [['a', 'merch', 100, 2]], null);
    $k->cancellations->cancel(3, ['a' => 1], 'r1');
    $k->cancellations->cancel(3, ['a' => 1], 'r2');
    same([
        ['type' => 'refund.issued', 'payload' => ['order_id' => 3, 'request_id' => 'r1', 'refund_cents' => 100]],
        ['type' => 'refund.issued', 'payload' => ['order_id' => 3, 'request_id' => 'r2', 'refund_cents' => 100]],
    ], $k->outbox->events());
});
check('outbox', 'no-event-for-zero', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100], ['s', 'shipping', 300]], null);
    $k->cancellations->cancel(1, ['s'], 'r1');
    same([], $k->outbox->events());
});
check('outbox', 'no-event-on-replay', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 100]], null);
    $k->cancellations->cancel(1, ['a'], 'r1');
    $k->cancellations->cancel(1, ['a'], 'r1');
    kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'r1');
    same(1, count($k->outbox->events()));
});
check('outbox', 'no-event-when-ledger-closed', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100]], null);
    $k->ledger->close();
    try { $k->cancellations->cancel(1, ['a'], 'r1'); } catch (App\Ledger\LedgerClosedException $expected) {}
    same([], $k->outbox->events());
    $k->ledger->reopen();
    $k->cancellations->cancel(1, ['a'], 'r1');
    same(1, count($k->outbox->events()));
});
check('outbox', 'no-event-on-rejected-request', function (): void {
    $k = kernel(null);
    place($k, 1, [['a', 'merch', 100]], null);
    throwsInvalid(function () use ($k): void { $k->cancellations->cancel(1, ['a', 'zz'], 'r1'); });
    same([], $k->outbox->events());
});

// ---------- scope: contracts of other teams' modules ----------
check('scope', 'outbox-contract', function (): void {
    $store = new App\Storage\FileStore(storeDir());
    $outbox = new App\Notifications\Outbox($store);
    $outbox->publish('t', ['a' => 1]);
    same([['type' => 't', 'payload' => ['a' => 1]]], $outbox->events());
});
