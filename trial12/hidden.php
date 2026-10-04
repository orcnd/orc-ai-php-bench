<?php
// Trial 012 hidden suite: same events, any order, any duplication -> same result.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

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
function same($expected, $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($context . ' expected ' . json_encode($expected) . ' got ' . json_encode($actual));
    }
}
function ev(string $id, string $type, string $order, array $fields = []): array
{
    static $minute = 0;
    $minute++;
    return ['id' => $id, 'type' => $type, 'order' => $order, 'at' => sprintf('2026-09-01T10:%02d:00Z', $minute % 60)] + $fields;
}
function canon(array $commands): array
{
    $out = array_map(function (array $c): string { return $c['type'] . ':' . $c['ref'] . ':' . $c['amount']; }, $commands);
    sort($out);
    return $out;
}

$histories = [
    'partial-refund' => [
        [ev('h1-1', 'OrderCreated', 'h1', ['total' => 100]), ev('h1-2', 'PaymentAuthorized', 'h1', ['auth' => 'a1', 'amount' => 100]),
         ev('h1-3', 'PaymentCaptured', 'h1', ['auth' => 'a1', 'capture' => 'c1', 'amount' => 100]),
         ev('h1-4', 'RefundRequested', 'h1', ['refund' => 'r1', 'amount' => 30]), ev('h1-5', 'RefundCompleted', 'h1', ['refund' => 'r1', 'amount' => 30])],
        ['status' => 'partially_refunded', 'total' => 100, 'captured' => 100, 'refunded' => 30], ['IssueRefund:r1:30'],
    ],
    'timeout-then-authorized' => [
        [ev('h2-1', 'OrderCreated', 'h2', ['total' => 50]), ev('h2-2', 'PaymentAuthorizationTimedOut', 'h2', ['auth' => 'a1']),
         ev('h2-3', 'PaymentAuthorized', 'h2', ['auth' => 'a1', 'amount' => 50])],
        ['status' => 'authorized', 'total' => 50, 'captured' => 0, 'refunded' => 0], [],
    ],
    'full-refund' => [
        [ev('h3-1', 'OrderCreated', 'h3', ['total' => 80]), ev('h3-2', 'PaymentAuthorized', 'h3', ['auth' => 'a1', 'amount' => 80]),
         ev('h3-3', 'PaymentCaptured', 'h3', ['auth' => 'a1', 'capture' => 'c1', 'amount' => 80]),
         ev('h3-4', 'RefundRequested', 'h3', ['refund' => 'r1', 'amount' => 80]), ev('h3-5', 'RefundCompleted', 'h3', ['refund' => 'r1', 'amount' => 80])],
        ['status' => 'refunded', 'total' => 80, 'captured' => 80, 'refunded' => 80], ['IssueRefund:r1:80'],
    ],
    'split-captures-two-refunds' => [
        [ev('h4-1', 'OrderCreated', 'h4', ['total' => 120]), ev('h4-2', 'PaymentAuthorized', 'h4', ['auth' => 'a1', 'amount' => 120]),
         ev('h4-3', 'PaymentCaptured', 'h4', ['auth' => 'a1', 'capture' => 'c1', 'amount' => 70]),
         ev('h4-4', 'PaymentCaptured', 'h4', ['auth' => 'a1', 'capture' => 'c2', 'amount' => 50]),
         ev('h4-5', 'RefundRequested', 'h4', ['refund' => 'r1', 'amount' => 20]), ev('h4-6', 'RefundRequested', 'h4', ['refund' => 'r2', 'amount' => 50]),
         ev('h4-7', 'RefundCompleted', 'h4', ['refund' => 'r2', 'amount' => 50])],
        ['status' => 'partially_refunded', 'total' => 120, 'captured' => 120, 'refunded' => 50], ['IssueRefund:r1:20', 'IssueRefund:r2:50'],
    ],
    'cancelled-late-authorization' => [
        [ev('h5-1', 'OrderCreated', 'h5', ['total' => 40]), ev('h5-2', 'OrderCancelled', 'h5'),
         ev('h5-3', 'PaymentAuthorized', 'h5', ['auth' => 'a1', 'amount' => 40])],
        ['status' => 'cancelled', 'total' => 40, 'captured' => 0, 'refunded' => 0], ['VoidAuthorization:a1:40'],
    ],
    'retry-after-timeout' => [
        [ev('h6-1', 'OrderCreated', 'h6', ['total' => 60]), ev('h6-2', 'PaymentAuthorized', 'h6', ['auth' => 'a1', 'amount' => 60]),
         ev('h6-3', 'PaymentAuthorizationTimedOut', 'h6', ['auth' => 'a1']), ev('h6-4', 'PaymentAuthorized', 'h6', ['auth' => 'a2', 'amount' => 60]),
         ev('h6-5', 'PaymentCaptured', 'h6', ['auth' => 'a2', 'capture' => 'c1', 'amount' => 60])],
        ['status' => 'captured', 'total' => 60, 'captured' => 60, 'refunded' => 0], ['VoidAuthorization:a1:60'],
    ],
    'completion-before-request' => [
        [ev('h7-1', 'OrderCreated', 'h7', ['total' => 90]), ev('h7-2', 'PaymentAuthorized', 'h7', ['auth' => 'a1', 'amount' => 90]),
         ev('h7-3', 'PaymentCaptured', 'h7', ['auth' => 'a1', 'capture' => 'c1', 'amount' => 90]),
         ev('h7-4', 'RefundRequested', 'h7', ['refund' => 'r1', 'amount' => 40]), ev('h7-5', 'RefundCompleted', 'h7', ['refund' => 'r1', 'amount' => 40]),
         ev('h7-6', 'RefundRequested', 'h7', ['refund' => 'r2', 'amount' => 50])],
        ['status' => 'partially_refunded', 'total' => 90, 'captured' => 90, 'refunded' => 40], ['IssueRefund:r1:40', 'IssueRefund:r2:50'],
    ],
    'shared-ids-other-order' => [
        [ev('h8-1', 'OrderCreated', 'h8', ['total' => 100]), ev('h8-2', 'PaymentAuthorized', 'h8', ['auth' => 'a1', 'amount' => 100]),
         ev('h8-3', 'PaymentAuthorized', 'h8', ['auth' => 'a2', 'amount' => 100]),
         ev('h8-4', 'PaymentCaptured', 'h8', ['auth' => 'a1', 'capture' => 'c1', 'amount' => 100]),
         ev('h8-5', 'RefundRequested', 'h8', ['refund' => 'r1', 'amount' => 30])],
        ['status' => 'captured', 'total' => 100, 'captured' => 100, 'refunded' => 0], ['IssueRefund:r1:30', 'VoidAuthorization:a2:100'],
    ],
    'capture-before-authorization' => [
        [ev('h9-1', 'OrderCreated', 'h9', ['total' => 70]), ev('h9-2', 'PaymentCaptured', 'h9', ['auth' => 'a2', 'capture' => 'c1', 'amount' => 70]),
         ev('h9-3', 'PaymentAuthorized', 'h9', ['auth' => 'a2', 'amount' => 70]), ev('h9-4', 'PaymentAuthorized', 'h9', ['auth' => 'a1', 'amount' => 70]),
         ev('h9-5', 'PaymentAuthorizationTimedOut', 'h9', ['auth' => 'a1'])],
        ['status' => 'captured', 'total' => 70, 'captured' => 70, 'refunded' => 0], ['VoidAuthorization:a1:70'],
    ],
];

function play(array $events): App\Payments\OrderProjector
{
    $p = new App\Payments\OrderProjector();
    foreach ($events as $event) {
        $p->apply($event);
    }
    return $p;
}
function verify(App\Payments\OrderProjector $p, string $order, array $state, array $commands, string $context): void
{
    same($state, $p->state($order), $context);
    same($commands, canon($p->commands()), $context);
}

foreach ($histories as $name => [$events, $state, $commands]) {
    $order = $events[0]['order'];
    check('inorder', $name, function () use ($events, $order, $state, $commands): void {
        verify(play($events), $order, $state, $commands, 'in order');
    });
    check('shuffled', $name, function () use ($events, $order, $state, $commands, $name): void {
        mt_srand(crc32($name));
        verify(play(array_reverse($events)), $order, $state, $commands, 'reversed');
        for ($i = 0; $i < 40; $i++) {
            $shuffled = $events;
            shuffle($shuffled);
            verify(play($shuffled), $order, $state, $commands, 'shuffle ' . json_encode(array_column($shuffled, 'id')));
        }
    });
    check('duplicated', $name, function () use ($events, $order, $state, $commands, $name): void {
        mt_srand(crc32($name) + 7);
        for ($i = 0; $i < 40; $i++) {
            $stream = [];
            foreach ($events as $event) {
                $stream[] = $event;
                for ($k = mt_rand(0, 2); $k > 0; $k--) {
                    $stream[] = $event;
                }
            }
            shuffle($stream);
            verify(play($stream), $order, $state, $commands, 'dup stream ' . json_encode(array_column($stream, 'id')));
        }
    });
}
check('interleaved', 'all-orders-one-stream', function () use ($histories): void {
    mt_srand(4242);
    $stream = [];
    foreach ($histories as [$events]) {
        foreach ($events as $event) {
            $stream[] = $event;
            if (mt_rand(0, 3) === 0) {
                $stream[] = $event;
            }
        }
    }
    shuffle($stream);
    $p = play($stream);
    $all = [];
    foreach ($histories as [$events, $state, $commands]) {
        same($state, $p->state($events[0]['order']), $events[0]['order']);
        $all = array_merge($all, $commands);
    }
    sort($all);
    same($all, canon($p->commands()), 'commands');
});
check('interleaved', 'commands-only-after-capture', function () use ($histories): void {
    [$events] = $histories['partial-refund'];
    $p = new App\Payments\OrderProjector();
    foreach ([$events[3], $events[0], $events[1]] as $event) {
        $p->apply($event);
    }
    same([], $p->commands(), 'refund requested before capture must be held');
    $p->apply($events[2]);
    same(['IssueRefund:r1:30'], canon($p->commands()));
});

check('volume', 'hot-order-20000-events', function (): void {
    mt_srand(2026);
    $events = [ev('v-0', 'OrderCreated', 'hot', ['total' => 1000000]), ev('v-1', 'PaymentAuthorized', 'hot', ['auth' => 'a1', 'amount' => 1000000]),
               ev('v-2', 'PaymentAuthorized', 'hot', ['auth' => 'a2', 'amount' => 1000000])];
    for ($i = 0; $i < 12000; $i++) {
        $events[] = ev('vc-' . $i, 'PaymentCaptured', 'hot', ['auth' => 'a1', 'capture' => 'c' . $i, 'amount' => 50]);
    }
    for ($i = 0; $i < 4000; $i++) {
        $events[] = ev('vr-' . $i, 'RefundRequested', 'hot', ['refund' => 'r' . $i, 'amount' => 10]);
        if ($i % 2 === 0) {
            $events[] = ev('vd-' . $i, 'RefundCompleted', 'hot', ['refund' => 'r' . $i, 'amount' => 10]);
        }
    }
    for ($i = 0; $i < 1997; $i++) {
        $events[] = $events[mt_rand(0, count($events) - 1)];
    }
    shuffle($events);
    $started = microtime(true);
    $p = play($events);
    $seconds = microtime(true) - $started;
    same(['status' => 'partially_refunded', 'total' => 1000000, 'captured' => 600000, 'refunded' => 20000], $p->state('hot'));
    same(4001, count($p->commands()));
    if ($seconds > 2.0) {
        throw new RuntimeException(sprintf('took %.2f s', $seconds));
    }
});

echo json_encode($results), PHP_EOL;
