<?php
// Trial 006 additions: crash consistency (process killed after the n-th
// store write) and CRM outbox maintenance windows.
declare(strict_types=1);

use App\Kernel;

/** Runs one request in a worker that is killed after its $crashAt-th put. Returns exit code. */
function crashWorker(string $dir, int $orderId, string $key, array $lines, int $crashAt): int
{
    global $argv;
    $env = array_merge(getenv(), ['STORE_CRASH_AT' => (string) $crashAt, 'XDEBUG_MODE' => 'off']);
    $cmd = implode(' ', array_map('escapeshellarg', [PHP_BINARY, __DIR__ . '/hidden_worker.php', $argv[1], $dir,
        LATER, '0', (string) $orderId, $key, json_encode($lines)]));
    $pipes = [];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc);
}
function bookings(Kernel $k, int $orderId, string $key): array
{
    return array_values(array_filter($k->ledger->entries(), function (array $e) use ($orderId, $key): bool {
        return $e['order_id'] === $orderId && $e['request_id'] === $key;
    }));
}
function mails(Kernel $k, int $orderId, string $key): int
{
    return count(array_filter(events($k), function (array $e) use ($orderId, $key): bool {
        return $e['payload']['order_id'] === $orderId && $e['payload']['request_id'] === $key;
    }));
}
/** Every crash point of one request: kill, then retry twice in fresh processes. */
function crashMatrix(array $lines, ?array $promo, array $request, callable $assert): void
{
    $crashed = 0;
    for ($at = 1; $at <= 12; $at++) {
        $dir = storeDir();
        $k = kernel($promo, $dir);
        place($k, 1, $lines, $promo);
        $code = crashWorker($dir, 1, 'k1', $request, $at);
        if ($code !== 137) {
            break;
        }
        $crashed++;
        $assert($dir, $at);
    }
    if ($crashed === 0) {
        throw new RuntimeException('request never wrote to the store');
    }
}

$crashLines = [['a', 'merch', 1000], ['b', 'merch', 600], ['c', 'credit', -300]];
check('crash', 'retry-same-key-every-point', function () use ($crashLines): void {
    crashMatrix($crashLines, null, ['a'], function (string $dir, int $at): void {
        $r1 = kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'k1');
        $k = kernelAt($dir, LATER);
        $r2 = $k->cancellations->cancel(1, ['a'], 'k1');
        same(response(1, 700), $r1);
        same($r1, $r2);
        same(1, count(bookings($k, 1, 'k1')));
    });
});
check('crash', 'exactly-one-mail-every-point', function () use ($crashLines): void {
    crashMatrix($crashLines, null, ['a'], function (string $dir, int $at): void {
        kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'k1');
        $k = kernelAt($dir, LATER);
        $k->cancellations->cancel(1, ['a'], 'k1');
        same(1, mails($k, 1, 'k1'));
    });
});
check('crash', 'other-key-first-every-point', function () use ($crashLines): void {
    crashMatrix($crashLines, null, ['a'], function (string $dir, int $at): void {
        $other = kernelAt($dir, LATER)->cancellations->cancel(1, ['a', 'b'], 'k2');
        $retry = kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'k1');
        $k = kernelAt($dir, LATER);
        same(1300, ledgerSum($k, 1));
        same(1300, $other['refund_cents'] + $retry['refund_cents']);
        same(count($k->ledger->entries()), count(events($k)));
    });
});
check('crash', 'units-every-point', function (): void {
    crashMatrix([['a', 'merch', 300, 3]], ['fixed', 100], ['a' => 1], function (string $dir, int $at): void {
        kernelAt($dir, LATER)->cancellations->cancel(1, ['a' => 1], 'k1');
        $k = kernelAt($dir, LATER);
        $rest = $k->cancellations->cancel(1, ['a'], 'k2');
        same(800, ledgerSum($k, 1));
        same(534, $rest['refund_cents']);
    });
});
check('crash', 'withdrawal-every-point', function (): void {
    crashMatrix([['a', 'merch', 1000], ['s', 'shipping', 400]], null, ['a'], function (string $dir, int $at): void {
        // The crashed attempt ran after the period (LATER); no delivery either way.
        $k = kernelAt($dir, LATER);
        same(response(1, 1000), $k->cancellations->cancel(1, ['a'], 'k1'));
        same(1000, ledgerSum($k, 1));
    });
});

// ---------- CRM outbox maintenance ----------
check('crash', 'outbox-down-money-first', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 500], ['b', 'merch', 200]], null);
    $k->outbox->takeDown();
    same(response(1, 500), $k->cancellations->cancel(1, ['a'], 'k1'));
    same(500, ledgerSum($k, 1));
    same([], events($k));
});
check('crash', 'outbox-catch-up-on-retry', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 500]], null);
    $k->outbox->takeDown();
    $k->cancellations->cancel(1, ['a'], 'k1');
    $k->outbox->restore();
    same(response(1, 500), kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'k1'));
    kernelAt($dir, LATER)->cancellations->cancel(1, ['a'], 'k1');
    same(1, mails($k, 1, 'k1'));
});
check('crash', 'outbox-catch-up-on-other-key', function (): void {
    $dir = storeDir();
    $k = kernel(null, $dir);
    place($k, 1, [['a', 'merch', 500], ['b', 'merch', 200]], null);
    $k->outbox->takeDown();
    $k->cancellations->cancel(1, ['a'], 'k1');
    $k->cancellations->cancel(1, ['b'], 'k2');
    $k->outbox->restore();
    kernelAt($dir, LATER)->cancellations->cancel(1, ['b'], 'k3');
    same(1, mails($k, 1, 'k1'));
    same(1, mails($k, 1, 'k2'));
    same(2, count(events($k)));
});
