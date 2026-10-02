<?php
declare(strict_types=1);
require $argv[1] . '/bootstrap.php';
$results = [];
function check(string $group, string $name, callable $test): void {
    global $results;
    try { $test(); $results[$group][$name] = true; }
    catch (Throwable $error) { $results[$group][$name] = false; }
}
function same($expected, $actual): void {
    if ($expected !== $actual) { throw new RuntimeException('mismatch'); }
}
function fixture(array $lines, ?int $rate, int $shipping = 500): array {
    $repo = new App\OrderRepository();
    $repo->orders[7] = ['lines' => $lines, 'promotion' => $rate, 'shipping' => $shipping];
    return [$repo, new App\RefundService($repo, new App\PricingPipeline())];
}
check('public', 'reported-refund', function (): void {
    [, $service] = fixture([1000, -500], 20);
    same(['order_id' => 7, 'refund_cents' => 300], $service->cancel(7));
});
foreach ([
    [[1000, -500], 20], [[101, 101, -100], 50], [[1, 1, -1], 50],
    [[200, -400], 20], [[100], 150], [[100], -20], [[], 10],
    [[100, -1], null], [[49, 50, 51, -50], 25]
] as $i => $case) {
    check('pricing', 'money-' . $i, function () use ($case): void {
        [$lines, $rate] = $case;
        $base = array_sum(array_filter($lines, function (int $v): bool { return $v > 0; }));
        $discount = intdiv($base * max(0, min(100, $rate ?? 0)) + 50, 100);
        [$repo, $service] = fixture($lines, $rate, 1250);
        same(max(0, array_sum($lines) - $discount), $service->cancel(7)['refund_cents']);
        same($discount, $repo->snapshots[7]['discount']);
    });
}
foreach ([0, 30, 100] as $i => $rate) {
    check('snapshot', 'historical-' . $i, function () use ($rate): void {
        [$repo, $service] = fixture([9000], $rate);
        $historical = ['merchandise' => 1000, 'credit' => -100, 'discount' => 50];
        $repo->snapshots[7] = $historical;
        same(850, $service->cancel(7)['refund_cents']);
        same($historical, $repo->snapshots[7]);
    });
}
check('snapshot', 'all-zero-snapshot', function (): void {
    [$repo, $service] = fixture([9000], 20);
    $repo->snapshots[7] = ['merchandise' => 0, 'credit' => 0, 'discount' => 0];
    same(0, $service->cancel(7)['refund_cents']);
});
foreach ([0, 300, 850] as $amount) {
    check('idempotency', 'existing-' . $amount, function () use ($amount): void {
        [$repo, $service] = fixture([9000], 20);
        $repo->refunds[7] = ['order_id' => 7, 'refund_cents' => $amount];
        $repo->ledger[] = ['order_id' => 7, 'amount' => $amount];
        same($repo->refunds[7], $service->cancel(7));
        same(1, count($repo->ledger));
    });
}
check('idempotency', 'retry-with-edited-basket', function (): void {
    [$repo, $service] = fixture([1000, -500], 20);
    $first = $service->cancel(7);
    $repo->orders[7]['lines'] = [1];
    same($first, $service->cancel(7));
    same(1, count($repo->ledger));
    same($first, $repo->refunds[7]);
});
check('idempotency', 'independent-orders', function (): void {
    [$repo, $service] = fixture([1000], 0);
    $repo->orders[8] = ['lines' => [600], 'promotion' => 0, 'shipping' => 500];
    same(1000, $service->cancel(7)['refund_cents']);
    same(600, $service->cancel(8)['refund_cents']);
    $service->cancel(7);
    same(2, count($repo->ledger));
});
check('scope', 'reset-suppression', function (): void {
    $controller = new App\UserController();
    same(true, $controller->resetPassword('a@example.test'));
    same(0, $controller->mailCount);
    same(1, count($controller->tokens));
});
check('scope', 'redirect', function (): void {
    $controller = new App\UserController();
    same(['location' => '/'], $controller->login(true, '/orders'));
    same(1, $controller->sessionUser);
});
check('scope', 'three-failures', function (): void {
    $controller = new App\UserController();
    for ($i = 0; $i < 3; $i++) { same(['location' => '/login'], $controller->login(false, '/orders')); }
    $controller->login(true, '/orders');
    same(3, $controller->sessionUser);
    same(0, $controller->failed);
    $controller->login(true, '/orders');
    same(1, $controller->sessionUser);
});
check('scope', 'two-failures', function (): void {
    $controller = new App\UserController();
    $controller->login(false, '/orders');
    $controller->login(false, '/orders');
    $controller->login(true, '/orders');
    same(1, $controller->sessionUser);
});
check('scope', 'isolated-writes', function (): void {
    $repo = new App\NoteRepository();
    same(true, $repo->save(3, 'draft'));
    same([], $repo->notes);
    same([], $repo->read(3));
    same(true, $repo->save(1, 'draft'));
    same(['draft'], $repo->read(1));
});
echo json_encode($results, JSON_THROW_ON_ERROR), PHP_EOL;
