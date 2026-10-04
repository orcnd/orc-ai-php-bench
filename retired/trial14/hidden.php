<?php
// Trial 014 hidden suite: properties over seeded random inputs (no fixed answers to memorise).
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Money\Allocation as A;

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
function ensure(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException($what);
    }
}
/** @return array{0: int, 1: array<string, int>} */
function randomCase(int $maxTotal, int $maxWeight): array
{
    $n = mt_rand(1, 12);
    $weights = [];
    for ($i = 0; $i < $n; $i++) {
        $weights['k' . dechex(mt_rand(0, 0xFFFFF))] = mt_rand(0, 4) === 0 ? 0 : mt_rand(1, $maxWeight);
    }
    $total = mt_rand(-$maxTotal, $maxTotal);
    return [$total, $weights];
}
/** |share - total*w/sum| < 1, checked exactly with GMP. */
function withinOneCent(int $share, int $total, int $weight, int $sum): bool
{
    $exactTimesSum = gmp_mul($total, $weight);
    $diff = gmp_sub(gmp_mul($share, $sum), $exactTimesSum);
    return gmp_cmp(gmp_abs($diff), $sum) < 0;
}
function allocProps(int $total, array $weights, string $ctx): array
{
    $shares = A::allocate($total, $weights);
    ensure(array_keys($shares) == array_keys($weights) || count(array_diff_key($weights, $shares)) === 0, "$ctx keys");
    ensure(array_sum($shares) === $total, "$ctx sum");
    $sum = array_sum($weights);
    $effective = $sum === 0 ? array_fill_keys(array_keys($weights), 1) : $weights;
    $sum = array_sum($effective);
    foreach ($effective as $key => $weight) {
        $share = $shares[$key];
        ensure(is_int($share), "$ctx int");
        ensure(withinOneCent($share, $total, $weight, $sum), "$ctx bound $key");
        ensure($share === 0 || ($share > 0) === ($total > 0), "$ctx sign $key");
        if ($weights[$key] === 0 && array_sum($weights) > 0) {
            ensure($share === 0, "$ctx zero weight $key");
        }
    }
    return $shares;
}

// ---------- allocate ----------
check('allocate', 'sum-bound-sign', function (): void {
    mt_srand(1401);
    for ($i = 0; $i < 3000; $i++) {
        [$total, $weights] = randomCase(1000000, 1000);
        allocProps($total, $weights, "case $i");
    }
});
check('allocate', 'order-independent', function (): void {
    mt_srand(1402);
    for ($i = 0; $i < 1500; $i++) {
        [$total, $weights] = randomCase(100000, 6);
        $keys = array_keys($weights);
        shuffle($keys);
        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $weights[$key];
        }
        $a = A::allocate($total, $weights);
        $b = A::allocate($total, $shuffled);
        foreach ($weights as $key => $_) {
            ensure($a[$key] === $b[$key], "case $i key $key");
        }
    }
});
check('allocate', 'scale-invariant', function (): void {
    mt_srand(1403);
    for ($i = 0; $i < 1500; $i++) {
        [$total, $weights] = randomCase(100000, 50);
        $factor = mt_rand(2, 20000);
        $scaled = array_map(function (int $w) use ($factor): int { return $w * $factor; }, $weights);
        ensure(A::allocate($total, $weights) == A::allocate($total, $scaled), "case $i factor $factor");
    }
});
check('allocate', 'huge-values', function (): void {
    mt_srand(1404);
    for ($i = 0; $i < 1000; $i++) {
        [$total, $weights] = randomCase(1000000000000, 1000000000);
        allocProps($total, $weights, "case $i");
    }
});
check('allocate', 'all-zero-and-single', function (): void {
    $s = A::allocate(10, ['b' => 0, 'a' => 0, 'c' => 0]);
    ensure($s['a'] === 4 && $s['b'] === 3 && $s['c'] === 3, 'all zero equal split, ties to smaller key ' . json_encode($s));
    ensure(A::allocate(-7, ['x' => 5]) === ['x' => -7], 'single');
    $t = A::allocate(-10, ['b' => 1, 'a' => 1, 'c' => 1]);
    ensure($t['a'] === -4 && $t['b'] === -3 && $t['c'] === -3, 'negative ties ' . json_encode($t));
});

// ---------- prorate ----------
function randomPeriod(): array
{
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', mt_rand(2023, 2030), mt_rand(1, 12)), new DateTimeZone('UTC'));
    $end = $start->modify('+' . mt_rand(1, 13) . ' month');
    $days = (int) $start->diff($end)->format('%a');
    return [$start, $end, $days];
}
function day(DateTimeImmutable $start, int $offset): string
{
    return $start->modify("+$offset day")->format('Y-m-d');
}
check('prorate', 'full-and-empty', function (): void {
    mt_srand(1405);
    for ($i = 0; $i < 500; $i++) {
        [$start, $end, $days] = randomPeriod();
        $amount = mt_rand(0, 10000000);
        ensure(A::prorate($amount, $start->format('Y-m-d'), $end->format('Y-m-d'), $start->format('Y-m-d'), $end->format('Y-m-d')) === $amount, "full $i");
        $d = day($start, mt_rand(0, $days));
        ensure(A::prorate($amount, $d, $d, $start->format('Y-m-d'), $end->format('Y-m-d')) === 0, "empty $i");
    }
});
check('prorate', 'additive', function (): void {
    mt_srand(1406);
    for ($i = 0; $i < 1500; $i++) {
        [$start, $end, $days] = randomPeriod();
        $amount = mt_rand(0, 10000000);
        $cuts = [0, $days];
        for ($k = mt_rand(1, 6); $k > 0; $k--) {
            $cuts[] = mt_rand(0, $days);
        }
        sort($cuts);
        $sum = 0;
        for ($k = 1; $k < count($cuts); $k++) {
            $sum += A::prorate($amount, day($start, $cuts[$k - 1]), day($start, $cuts[$k]), $start->format('Y-m-d'), $end->format('Y-m-d'));
        }
        ensure($sum === $amount, "case $i cuts " . json_encode($cuts));
    }
});
check('prorate', 'bounded', function (): void {
    mt_srand(1407);
    for ($i = 0; $i < 1500; $i++) {
        [$start, $end, $days] = randomPeriod();
        $amount = mt_rand(0, 10000000);
        $a = mt_rand(0, $days);
        $b = mt_rand($a, $days);
        $p = A::prorate($amount, day($start, $a), day($start, $b), $start->format('Y-m-d'), $end->format('Y-m-d'));
        ensure(abs($p * $days - $amount * ($b - $a)) < $days, "case $i");
    }
});
check('prorate', 'dst-and-leap', function (): void {
    ensure(A::prorate(2900, '2028-02-01', '2028-03-01', '2028-02-01', '2028-03-01') === 2900, 'leap');
    ensure(A::prorate(3100, '2026-03-01', '2026-04-01', '2026-03-01', '2026-04-01') === 3100, 'march');
    $tz = date_default_timezone_get();
    date_default_timezone_set('Europe/Berlin');
    try {
        ensure(A::prorate(3100, '2026-03-29', '2026-03-30', '2026-03-01', '2026-04-01') === 100, 'dst day in Berlin');
        ensure(A::prorate(3100, '2026-03-01', '2026-03-29', '2026-03-01', '2026-04-01') === 2800, 'before dst in Berlin');
    } finally {
        date_default_timezone_set($tz);
    }
});

// ---------- installments ----------
check('installments', 'properties', function (): void {
    mt_srand(1408);
    for ($i = 0; $i < 3000; $i++) {
        $total = mt_rand(0, 100000000);
        $count = mt_rand(1, 48);
        $parts = A::installments($total, $count);
        ensure(count($parts) === $count && array_sum($parts) === $total, "sum $i");
        ensure(max($parts) - min($parts) <= 1, "spread $i");
        for ($k = 1; $k < $count; $k++) {
            ensure($parts[$k] <= $parts[$k - 1], "order $i");
        }
    }
});

echo json_encode($results), PHP_EOL;
