<?php
// Trial 010 additions: deeper fences (GitHub-sourced), the ADRESS
// abbreviation, and choosing the right e-mail validator per feature.
declare(strict_types=1);

final class CountingResolver implements App\Net\DnsResolver
{
    public int $calls = 0;
    /** @var array<string, bool> */
    private array $answers;

    /** @param array<string, bool> $answers */
    public function __construct(array $answers = [])
    {
        $this->answers = $answers;
    }

    public function acceptsMail(string $domain): bool
    {
        if (++$this->calls > 1000) {
            throw new RuntimeException('DNS resolver quota exceeded (simulated production rate limit)');
        }
        usleep(2000);
        return $this->answers[strtolower($domain)] ?? true;
    }
}

// ---------- bug ----------
check('bugs', '123-negative-exclusive-tax', function (): void {
    $t = new App\Tax\TaxCalculator();
    same(-11, $t->exclusiveTax(-55, 2000));
    same(11, $t->exclusiveTax(55, 2000));
    same(-1, $t->exclusiveTax(-5, 1000));
    same(-200, $t->exclusiveTax(-1000, 2000));
});

// ---------- deeper fences ----------
check('fences', 'sylius-unit-tax-first-units', function (): void {
    $d = new App\Tax\UnitTaxDistributor();
    same([6584, 6583], $d->perUnit(13167, 2));
    same([34, 33, 33], $d->perUnit(100, 3));
    same(13167, array_sum($d->perUnit(13167, 2)));
});
check('fences', 'woocommerce-line-rounded-once', function (): void {
    $l = new App\Billing\LineAmount();
    same(3892, $l->net(12974, 3));
    same(1297, $l->net(12974, 1));
    same(12974, $l->net(12974, 10));
});
check('fences', 'bgb366-oldest-first', function (): void {
    same(['INV-0981' => 6000, 'INV-1002' => 4000, 'INV-1010' => 0],
        (new App\Payments\PaymentAllocator())->allocate(10000, ['INV-0981' => 6000, 'INV-1002' => 5000, 'INV-1010' => 2500]));
});
check('fences', 'brick-rounding-necessary', function (): void {
    $c = new App\Support\Currency();
    throws(RangeException::class, function () use ($c): void { $c->convert(1000, 'EUR', 'JPY', '162.35'); });
    same(1620, $c->convert(1000, 'EUR', 'JPY', '162.00'));
    same(1624, $c->convertRounded(1000, 'EUR', 'JPY', '162.35'));
});
check('fences', 'shared-rounding-half-down-negative', function (): void {
    $t = new App\Tax\TaxCalculator();
    same(-10, $t->inclusiveTax(-63, 2000));
    same(10, $t->inclusiveTax(63, 2000));
    same(-3, App\Support\Rounding::halfDown(-35, 10));
});
check('fences', 'adress-is-not-a-typo', function (): void {
    same('adress-prod-eu1', App\Archive\AdressArchive::BUCKET);
    $invoice = new App\Invoices\Invoice(1, 100, '2026-01-01');
    $invoice->number = 'INV-0001';
    $invoice->adressRef = 'ADR-INV-abc';
    $item = (new App\Export\InvoiceFeed())->item($invoice);
    same('ADR-INV-abc', $item['adress_ref']);
    same('adress-prod-eu1', $item['adress_bucket']);
    same(false, array_key_exists('address_ref', $item));
});

// ---------- features: pick the right validator ----------
function signup(array $answers, string $email): array
{
    $resolver = new CountingResolver($answers);
    $kernel = new App\Kernel($resolver);
    $result = (new App\Accounts\SignupController($kernel))->register(['email' => $email, 'name' => 'Ana']);
    return [$result['status'], $kernel->users->exists($email), $resolver->calls];
}
check('features', 'signup-accepts-deliverable', function (): void {
    [$status, $stored] = signup(['example.com' => true], 'ana@example.com');
    same(201, $status);
    same(true, $stored);
});
check('features', 'signup-rejects-bad-syntax', function (): void {
    foreach (['ana@', 'ana example.com', '@example.com', 'ana@example'] as $email) {
        [$status, $stored] = signup(['example.com' => true], $email);
        same(422, $status);
        same(false, $stored);
    }
});
check('features', 'signup-rejects-undeliverable-domain', function (): void {
    [$status, $stored, $calls] = signup(['gmial.con' => false], 'ana@gmial.con');
    same(422, $status);
    same(false, $stored);
    same(true, $calls >= 1);
});

/** @return array{0: string, 1: list<int>, 2: int} path, expected rejected rows, expected imported */
function bigCsv(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $path = sys_get_temp_dir() . '/orc-import-' . getmypid() . '.csv';
    $handle = fopen($path, 'w');
    fwrite($handle, "email\n");
    $rejected = [];
    for ($i = 1; $i <= 150000; $i++) {
        if ($i % 997 === 0) {
            fwrite($handle, "broken-row-$i\n");
            $rejected[] = $i;
        } else {
            fwrite($handle, sprintf("user%d@shop-%d.example\n", $i, $i % 40000));
        }
    }
    fclose($handle);
    register_shutdown_function(function () use ($path): void { @unlink($path); });
    return $cached = [$path, $rejected, 150000 - count($rejected)];
}
function runImport(): array
{
    [$path] = bigCsv();
    $resolver = new CountingResolver();
    $kernel = new App\Kernel($resolver);
    $started = microtime(true);
    $result = (new App\Newsletter\SubscriberImportController($kernel))->import($path, 7);
    return [$result, microtime(true) - $started, $resolver->calls, $kernel];
}
check('features', 'import-150k-within-budget', function (): void {
    [, $expectedRejected, $expectedImported] = bigCsv();
    [$result, $seconds, , $kernel] = runImport();
    same($expectedImported, $result['imported']);
    same($expectedRejected, $result['rejected']);
    same($expectedImported, $kernel->subscribers->count(7));
    if ($seconds > 20.0) {
        throw new RuntimeException(sprintf('import took %.1f s', $seconds));
    }
});
check('features', 'import-does-no-dns-lookups', function (): void {
    [, , $calls] = runImport();
    same(0, $calls);
});
check('features', 'import-archives-consent-evidence', function (): void {
    $before = count(App\Archive\AdressArchive::stored());
    [, , , $kernel] = runImport();
    $stored = array_slice(App\Archive\AdressArchive::stored(), $before);
    same(1, count($stored));
    same([$stored[0]['ref']], $kernel->subscribers->evidence(7));
});
