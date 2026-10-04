<?php
// Trial 016 hidden suite: each group is a rejected AI PR (AIDev) ported to PHP; the checks encode
// what the maintainer said the agent got wrong. See README.md for the mapping.
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
            fwrite(STDERR, "$group/$name: " . get_class($error) . ' ' . substr($error->getMessage(), 0, 300) . "\n");
        }
    }
}
/** @param mixed $expected @param mixed $actual */
function same($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('expected ' . json_encode($expected) . ' got ' . json_encode($actual));
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
        throw new RuntimeException('expected ' . $class . ' got ' . get_class($e) . ': ' . $e->getMessage());
    }
    throw new RuntimeException('expected ' . $class);
}

// c01 phiki#76: a rule matching without advancing must be skipped and the next rule tried.
function tokenize(array $cases): ?array
{
    global $argv;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/hidden_tokenize.php') . ' ' . escapeshellarg($argv[1]);
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], json_encode($cases));
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    $out = '';
    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline) {
        $out .= (string) stream_get_contents($pipes[1]);
        if (!proc_get_status($proc)['running']) {
            $out .= (string) stream_get_contents($pipes[1]);
            break;
        }
        usleep(20000);
    }
    if (proc_get_status($proc)['running']) {
        proc_terminate($proc, 9);
        return null;
    }
    proc_close($proc);
    return json_decode($out, true);
}
check('c01-tokenizer', 'no-hang-and-next-rule', function (): void {
    $got = tokenize([
        ["key = value", [['ws', '/\G\s*/'], ['word', '/\G\w+/'], ['op', '/\G=/']]],
        ["bbb", [['opt', '/\Ga*/'], ['word', '/\G\w+/']]],
    ]);
    if ($got === null) {
        throw new RuntimeException('tokenizer did not terminate');
    }
    same([['word', 'key'], ['ws', ' '], ['op', '='], ['ws', ' '], ['word', 'value']], $got[0]);
    same([['word', 'bbb']], $got[1]);
});
check('c01-tokenizer', 'fallback-text-when-nothing-advances', function (): void {
    $got = tokenize([["!?", [['opt', '/\Ga*/'], ['lookahead', '/\G(?=!)/']]]]);
    if ($got === null) {
        throw new RuntimeException('tokenizer did not terminate');
    }
    same([['text', '!'], ['text', '?']], $got[0]);
});

// c02 azure-service-operator#4864: explicit falsy overrides win; null keeps base; lists replace.
check('c02-merger', 'falsy-overrides', function (): void {
    $m = new App\Config\Merger();
    $base = ['features' => ['export' => ['enabled' => true, 'limit' => 50, 'label' => 'Export']], 'retries' => 3];
    same(['features' => ['export' => ['enabled' => false, 'limit' => 0, 'label' => '']], 'retries' => 3],
        $m->merge($base, ['features' => ['export' => ['enabled' => false, 'limit' => 0, 'label' => '']]]));
});
check('c02-merger', 'null-keeps-lists-replace', function (): void {
    $m = new App\Config\Merger();
    same(['hosts' => ['b'], 'tls' => true, 'tags' => []],
        $m->merge(['hosts' => ['a1', 'a2'], 'tls' => true, 'tags' => ['x']], ['hosts' => ['b'], 'tls' => null, 'tags' => []]));
    same(['db' => ['host' => 'h', 'opts' => ['x' => 1, 'y' => 2]]],
        $m->merge(['db' => ['host' => 'h', 'opts' => ['x' => 1]]], ['db' => ['opts' => ['y' => 2]]]));
});

// c03 camunda#37452: all correlations, keyed by (message, subscription); redelivery adds nothing.
check('c03-correlations', 'all-subscriptions', function (): void {
    $s = new App\Workflow\CorrelationStore();
    $s->record('m1', 'sub-a', 'p1');
    $s->record('m1', 'sub-b', 'p2');
    $s->record('m1', 'sub-c', 'p3');
    $s->record('m2', 'sub-a', 'p9');
    same(['sub-a', 'sub-b', 'sub-c'], array_column($s->correlations('m1'), 'subscription'));
    same(['p9'], array_column($s->correlations('m2'), 'process'));
});
check('c03-correlations', 'redelivery-idempotent', function (): void {
    $s = new App\Workflow\CorrelationStore();
    $s->record('m1', 'sub-a', 'p1');
    $s->record('m1', 'sub-b', 'p2');
    $s->record('m1', 'sub-a', 'p1');
    same([['subscription' => 'sub-a', 'process' => 'p1'], ['subscription' => 'sub-b', 'process' => 'p2']], $s->correlations('m1'));
});

// c04 msbuild#12591: fix the caller, keep the public tryParse contract.
check('c04-version-contract', 'condition-falls-back', function (): void {
    $c = new App\Versioning\Condition();
    same(strcmp('1.2.3.4.5', '1.2') >= 0, $c->evaluate('1.2.3.4.5', '>=', '1.2'));
    same(true, $c->evaluate('1.2.x', '!=', '1.2'));
    same(true, $c->evaluate('2.10', '>', '2.9'));
});
check('c04-version-contract', 'tryparse-contract-unchanged', function (): void {
    throws(App\Versioning\InvalidVersion::class, function (): void { App\Versioning\Version::tryParse('1.2.3.4.5'); });
    throws(App\Versioning\InvalidVersion::class, function (): void { App\Versioning\Version::tryParse('1..2'); });
    same(false, App\Versioning\Version::tryParse('beta'));
    same(true, App\Versioning\Version::tryParse('1.2.3', $v));
});

// c05 Homebrew#20658: XDG support without breaking existing users.
function oneOf(array $allowed, $actual): void
{
    if (!in_array($actual, $allowed, true)) {
        throw new RuntimeException('expected one of ' . json_encode($allowed) . ' got ' . json_encode($actual));
    }
}
check('c05-xdg', 'combinations', function (): void {
    $r = new App\Config\PathResolver();
    $none = function (string $p): bool { return false; };
    $legacyExists = function (string $p): bool { return $p === '/home/ana/.acmerc'; };
    // Existing users keep their file, whether or not XDG_CONFIG_HOME is set.
    same('/home/ana/.acmerc', $r->configFile(['HOME' => '/home/ana'], $legacyExists));
    same('/home/ana/.acmerc', $r->configFile(['HOME' => '/home/ana', 'XDG_CONFIG_HOME' => '/home/ana/.config'], $legacyExists));
    same('/home/ana/.config/acme/config', $r->configFile(['HOME' => '/home/ana', 'XDG_CONFIG_HOME' => '/home/ana/.config'], $none));
    // XDG unset and no legacy file: the old default or the XDG default are both acceptable.
    oneOf(['/home/ana/.acmerc', '/home/ana/.config/acme/config'], $r->configFile(['HOME' => '/home/ana'], $none));
});
check('c05-xdg', 'invalid-xdg-ignored', function (): void {
    $r = new App\Config\PathResolver();
    $none = function (string $p): bool { return false; };
    $legacyExists = function (string $p): bool { return $p === '/home/ana/.acmerc'; };
    oneOf(['/home/ana/.acmerc', '/home/ana/.config/acme/config'], $r->configFile(['HOME' => '/home/ana', 'XDG_CONFIG_HOME' => ''], $none));
    oneOf(['/home/ana/.acmerc', '/home/ana/.config/acme/config'], $r->configFile(['HOME' => '/home/ana', 'XDG_CONFIG_HOME' => 'relative/dir'], $none));
    same('/home/ana/.acmerc', $r->configFile(['HOME' => '/home/ana', 'XDG_CONFIG_HOME' => 'relative/dir'], $legacyExists));
});

// c06 infrahub#6767: the new rule must not break existing schemas: warning unless strict.
check('c06-schema', 'relationship-warning-or-strict-error', function (): void {
    $v = new App\Schema\Validator();
    $lax = $v->validate(['attributes' => ['name'], 'relationships' => ['class', 'owner']]);
    same([], $lax['errors']);
    same(1, count($lax['warnings']));
    $strict = $v->validate(['attributes' => ['name'], 'relationships' => ['class']], true);
    same(1, count($strict['errors']));
});
check('c06-schema', 'attributes-unchanged', function (): void {
    $v = new App\Schema\Validator();
    same(1, count($v->validate(['attributes' => ['List']])['errors']));
    same(['errors' => [], 'warnings' => []], $v->validate(['attributes' => ['title'], 'relationships' => ['author']]));
});

// c07 vscode#268211: challenge URL is the only candidate.
check('c07-discovery', 'challenge-only', function (): void {
    $d = new App\Auth\DiscoveryUrls();
    same(['https://auth.example/meta/r1'], $d->candidates('https://api.example/v1/files', 'https://auth.example/meta/r1'));
});
check('c07-discovery', 'well-known-fallback', function (): void {
    $d = new App\Auth\DiscoveryUrls();
    same(['https://api.example/.well-known/oauth-protected-resource/v1/files', 'https://api.example/.well-known/oauth-protected-resource'],
        $d->candidates('https://api.example/v1/files'));
    same(['https://api.example:8443/.well-known/oauth-protected-resource'], $d->candidates('https://api.example:8443/'));
});

// c08 ArcadeDB#2587: each record once, then paginate.
check('c08-search', 'no-duplicates-stable-pages', function (): void {
    $i = new App\Search\TagIndex();
    for ($n = 1; $n <= 12; $n++) {
        $i->add($n, "A$n", sprintf('2026-09-%02d', 30 - $n % 5), $n % 2 === 0 ? ['php', 'billing'] : ['php']);
    }
    $all = $i->search(['php', 'billing'], 100);
    same(12, count($all));
    same(12, count(array_unique($all)));
    $pages = array_merge($i->search(['php', 'billing'], 5, 0), $i->search(['php', 'billing'], 5, 5), $i->search(['php', 'billing'], 5, 10));
    same($all, $pages);
});

// c09 fromthepage#4811: verbatim keeps the marker; reading text joins the word.
check('c09-export', 'reading-joins', function (): void {
    $e = new App\Export\TranscriptExporter();
    same("Die Verwaltung tagte.\n\nZweiter Absatz", $e->export("Die Ver¬\nwaltung tagte.\n\nZweiter\nAbsatz", 'reading'));
});
check('c09-export', 'verbatim-keeps-marker', function (): void {
    $e = new App\Export\TranscriptExporter();
    same("Die Ver¬\nwaltung tagte.", $e->export("Die Ver¬\nwaltung tagte.", 'verbatim'));
});

// c10 django-upgrade#598: never rewrite calls the upgrader does not fully understand.
check('c10-rewriter', 'rewrites-simple', function (): void {
    $r = new App\Rules\CallRewriter();
    same('discount(percent: 10, stackable: true)', $r->upgrade('discount(10, true)'));
    oneOf(['total > 100 ? discount(percent: min(15, tier * 5), stackable: false) : discount(percent: 5)',
           'total > 100 ? discount(min(15, tier * 5), false) : discount(5)'],
        $r->upgrade('total > 100 ? discount(min(15, tier * 5), false) : discount(5)'));
    same('discount(percent: 5)', $r->upgrade('discount(5)'));
    same("discount(percent: 10, stackable: true) && note('a, b')", $r->upgrade("discount(10, true) && note('a, b')"));
});
check('c10-rewriter', 'leaves-unknown-forms', function (): void {
    $r = new App\Rules\CallRewriter();
    same('discount(10, true, 3)', $r->upgrade('discount(10, true, 3)'));
    oneOf(['discount(10, stackable: true)', 'discount(percent: 10, stackable: true)'], $r->upgrade('discount(10, stackable: true)'));
    same('discount(percent: 10)', $r->upgrade('discount(percent: 10)'));
    same('mydiscount(10, true)', $r->upgrade('mydiscount(10, true)'));
});

// c11 marimo#3806: comments, '#' inside strings, multiple statements.
check('c11-suppression', 'comments-and-strings', function (): void {
    $s = new App\Notebook\Suppression();
    same(true, $s->isSuppressed('total; # hide this'));
    same(false, $s->isSuppressed('label = "a;"'));
    same(false, $s->isSuppressed("x = '#;'"));
    same(true, $s->isSuppressed("a = 1; b = '#'; total;"));
    same(false, $s->isSuppressed("total # ;"));
    same(true, $s->isSuppressed("x = 1\ntotal;  # done\n"));
});

// c12 opteryx#2860: push the limit down only as far as correctness allows.
function topRows(): array
{
    $rows = [];
    for ($i = 1; $i <= 200; $i++) {
        $rows[] = ['id' => $i, 'amount' => ($i * 37) % 997, 'currency' => $i % 3 === 0 ? 'USD' : 'EUR'];
    }
    return $rows;
}
check('c12-topn', 'order-by-source-column', function (): void {
    $t = new App\Query\TopN();
    $conv = function (array $r): int { return $r['currency'] === 'USD' ? intdiv((int) $r['amount'] * 92, 100) : (int) $r['amount']; };
    $out = $t->run(topRows(), ['id' => 'id', 'amount' => 'amount', 'eur' => $conv], 'amount', true, 10);
    $amounts = array_column(topRows(), 'amount');
    rsort($amounts);
    same(array_slice($amounts, 0, 10), array_column($out, 'amount'));
    same(10, $t->computations);
});
check('c12-topn', 'order-by-computed-column', function (): void {
    $t = new App\Query\TopN();
    $conv = function (array $r): int { return $r['currency'] === 'USD' ? intdiv((int) $r['amount'] * 92, 100) * 10 + 1 : (int) $r['amount'] * 10; };
    $label = function (array $r): string { return 'order-' . $r['id']; };
    $out = $t->run(topRows(), ['id' => 'id', 'eur' => $conv, 'label' => $label], 'eur', true, 5);
    $all = array_map($conv, topRows());
    rsort($all);
    same(array_slice($all, 0, 5), array_column($out, 'eur'));
    same(200 + 5, $t->computations);
});

// c13 aspnetcore#62623: object-level validation only after property rules pass.
final class Booking
{
    public ?string $from;
    public ?string $to;
    public int $validateCalls = 0;
    public function __construct(?string $from, ?string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }
    public function validate(): ?string
    {
        $this->validateCalls++;
        return strcmp((string) $this->to, (string) $this->from) < 0 ? 'end before start' : null;
    }
}
check('c13-validation', 'object-level-runs', function (): void {
    $rules = ['from' => function ($v): ?string { return $v === null ? 'required' : null; }, 'to' => function ($v): ?string { return $v === null ? 'required' : null; }];
    same(['' => 'end before start'], (new App\Validation\EntityValidator())->validate(new Booking('2026-10-05', '2026-10-01'), $rules));
    same([], (new App\Validation\EntityValidator())->validate(new Booking('2026-10-01', '2026-10-05'), $rules));
});
check('c13-validation', 'skipped-when-properties-fail', function (): void {
    $rules = ['from' => function ($v): ?string { return $v === null ? 'required' : null; }];
    $b = new Booking(null, '2026-10-01');
    same(['from' => 'required'], (new App\Validation\EntityValidator())->validate($b, $rules));
    same(0, $b->validateCalls);
});

// c14 lakasir#328: soft deletes; reuse barcodes of deleted products, never two active owners.
check('c14-barcodes', 'reuse-after-delete', function (): void {
    $r = new App\Catalog\ProductRepository();
    $a = $r->create('Chair', '400123');
    $r->delete($a->id, '2026-10-01');
    $b = $r->create('Stool', '400123');
    same('Stool', $r->findByBarcode('400123') === null ? null : $r->findByBarcode('400123')->name);
    throws(App\Catalog\DuplicateBarcode::class, function () use ($r): void { $r->create('Bench', '400123'); });
});
check('c14-barcodes', 'restore-conflict-and-convention', function () use ($argv): void {
    $r = new App\Catalog\ProductRepository();
    $a = $r->create('Chair', '400123');
    $r->delete($a->id, '2026-10-01');
    $r->create('Stool', '400123');
    throws(App\Catalog\DuplicateBarcode::class, function () use ($r, $a): void { $r->restore($a->id); });
    same(true, $a->isTrashed());
    $code = '';
    foreach (glob($argv[1] . '/app/Catalog/*.php') ?: [] as $file) {
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
    }
    same(0, preg_match('/is_?deleted/i', $code));
});

// c15 Cloudlog#3335: fix every occurrence, not only the files named in the issue.
check('c15-deprecations', 'no-implicit-nullable-anywhere', function () use ($argv): void {
    $offenders = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1] . '/app'));
    foreach ($it as $file) {
        if (substr((string) $file, -4) !== '.php') {
            continue;
        }
        $code = (string) file_get_contents((string) $file);
        if (preg_match('/(?<![?\w\\\\])(?:[A-Z][\w\\\\]*|string|int|float|bool|array|callable|iterable|object)\s+\$\w+\s*=\s*null/', $code) === 1) {
            $offenders[] = basename((string) $file);
        }
    }
    same([], $offenders);
});
check('c15-deprecations', 'behaviour-unchanged', function (): void {
    same('Dun-00042', (new App\Billing\DunningService())->label(null, 42));
    same('X-00007', (new App\Billing\CreditsService())->label('X', 7));
    same(false, (new App\Billing\PayoutsService())->hasClock());
});

// c16 cosh RPKI: maxLength defaults to the prefix length; malformed ROAs and AS0 never authorise.
function rpki(array $roas): App\Routing\OriginValidator
{
    $v = new App\Routing\OriginValidator();
    foreach ($roas as $r) {
        $v->addRoa($r[0], $r[1], $r[2] ?? null);
    }
    return $v;
}
check('c16-rpki', 'existing-results', function (): void {
    $v = rpki([['198.51.100.0/22', 64500, 24]]);
    same('valid', $v->validate('198.51.100.0/22', 64500));
    same('invalid', $v->validate('198.51.100.0/22', 64511));
    same('not-found', $v->validate('203.0.113.0/24', 64500));
    same('not-found', $v->validate('198.51.96.0/21', 64500));
});
check('c16-rpki', 'maxlength-respected', function (): void {
    $v = rpki([['198.51.100.0/22', 64500, 24]]);
    same('valid', $v->validate('198.51.101.0/24', 64500));
    same('invalid', $v->validate('198.51.100.0/25', 64500));
});
check('c16-rpki', 'absent-maxlength-is-exact', function (): void {
    $v = rpki([['192.0.2.0/24', 64500]]);
    same('valid', $v->validate('192.0.2.0/24', 64500));
    same('invalid', $v->validate('192.0.2.128/25', 64500));
});
check('c16-rpki', 'any-matching-roa', function (): void {
    $v = rpki([['10.0.0.0/8', 64500, 16], ['10.1.0.0/16', 64500, 24], ['10.1.0.0/16', 64501, 24]]);
    same('valid', $v->validate('10.1.2.0/24', 64500));
    same('valid', $v->validate('10.1.2.0/24', 64501));
    same('invalid', $v->validate('10.2.2.0/24', 64500));
});
check('c16-rpki', 'as0-never-matches', function (): void {
    $v = rpki([['192.0.2.0/24', 0, 32]]);
    same('invalid', $v->validate('192.0.2.0/24', 0));
    same('invalid', $v->validate('192.0.2.0/25', 64500));
});
check('c16-rpki', 'malformed-roa-discarded', function (): void {
    $v = rpki([['192.0.2.0/24', 64500, 16]]);
    same('not-found', $v->validate('192.0.2.0/24', 64500));
    same('not-found', $v->validate('192.0.2.0/24', 64511));
    $w = rpki([['192.0.2.0/24', 64500, 33]]);
    same('not-found', $w->validate('192.0.2.0/24', 64500));
});
check('c16-rpki', 'ipv6', function (): void {
    $v = rpki([['2001:db8::/32', 64500, 48], ['198.51.100.0/24', 64500, 24]]);
    same('valid', $v->validate('2001:db8:1::/48', 64500));
    same('invalid', $v->validate('2001:db8:1::/49', 64500));
    same('not-found', $v->validate('2001:db9::/32', 64500));
});

// c17 spark-rapids: "ignore" is a save mode (no-op when the table exists), not "ignore errors".
check('c17-savemode', 'existing-modes', function (): void {
    $w = new App\Storage\TableWriter();
    same(1, $w->write('t', [['id' => 1]]));
    throws(App\Storage\TableExists::class, function () use ($w): void { $w->write('t', [['id' => 2]]); });
    same(1, $w->write('t', [['id' => 3]], 'append'));
    same([['id' => 1], ['id' => 3]], $w->read('t'));
    throws(App\Storage\InvalidRow::class, function () use ($w): void { $w->write('u', [['id' => 1], ['x' => 2]]); });
    same(null, $w->read('u'));
});
check('c17-savemode', 'ignore-existing-is-noop', function (): void {
    $w = new App\Storage\TableWriter();
    $w->write('snap', [['id' => 1, 'v' => 'a']]);
    same(0, $w->write('snap', [['id' => 2, 'v' => 'b']], 'ignore'));
    same([['id' => 1, 'v' => 'a']], $w->read('snap'));
    $w->write('empty', []);
    same(0, $w->write('empty', [['id' => 9]], 'ignore'));
    same([], $w->read('empty'));
});
check('c17-savemode', 'ignore-missing-creates', function (): void {
    $w = new App\Storage\TableWriter();
    same(2, $w->write('snap', [['id' => 1], ['id' => 2]], 'ignore'));
    same([['id' => 1], ['id' => 2]], $w->read('snap'));
    $w->drop('snap');
    same(1, $w->write('snap', [['id' => 5]], 'ignore'));
    same([['id' => 5]], $w->read('snap'));
});
check('c17-savemode', 'ignore-does-not-swallow-errors', function (): void {
    $w = new App\Storage\TableWriter();
    throws(App\Storage\InvalidRow::class, function () use ($w): void { $w->write('snap', [['id' => 1], ['v' => 2]], 'ignore'); });
    same(null, $w->read('snap'));
});
check('c17-savemode', 'ignore-existing-skips-the-data', function (): void {
    $w = new App\Storage\TableWriter();
    $w->write('snap', [['id' => 1]]);
    same(0, $w->write('snap', [['v' => 'no id']], 'ignore'));
    same([['id' => 1]], $w->read('snap'));
});
check('c17-savemode', 'spark-mode-names', function (): void {
    $w = new App\Storage\TableWriter();
    $w->write('snap', [['id' => 1]]);
    same(0, $w->write('snap', [['id' => 2]], 'Ignore'));
    same(0, $w->write('snap', [['id' => 2]], 'IGNORE'));
    throws(App\Storage\TableExists::class, function () use ($w): void { $w->write('snap', [['id' => 2]], 'errorifexists'); });
    same(1, $w->write('snap', [['id' => 2]], 'Append'));
    same([['id' => 1], ['id' => 2]], $w->read('snap'));
});

// c18 fromthepage: one subject per (collection, category, normalised title); first spelling kept.
check('c18-subjects', 'existing', function (): void {
    $s = new App\Archive\SubjectIndex();
    $a = $s->link(1, ' Smith, John ', 'person');
    same('Smith, John', $s->title($a));
    same(1, $s->count(1));
});
check('c18-subjects', 'case-and-spaces', function (): void {
    $s = new App\Archive\SubjectIndex();
    $a = $s->link(1, 'Smith, John', 'person');
    same($a, $s->link(1, 'smith,  John', 'person'));
    same($a, $s->link(1, "SMITH,\tJOHN ", 'person'));
    same(1, $s->count(1));
    same('Smith, John', $s->title($a));
});
check('c18-subjects', 'unicode', function (): void {
    $s = new App\Archive\SubjectIndex();
    $a = $s->link(1, 'Müller, Jörg', 'person');
    same($a, $s->link(1, 'MÜLLER, JÖRG', 'person'));
    $b = $s->link(1, 'Şahan, Ayşe', 'person');
    same($b, $s->link(1, 'ŞAHAN, AYŞE', 'person'));
    same($a, $s->link(1, "Müller,\u{00A0}Jörg", 'person'));
    same(2, $s->count(1));
});
check('c18-subjects', 'scoped', function (): void {
    $s = new App\Archive\SubjectIndex();
    $person = $s->link(1, 'Washington', 'person');
    $place = $s->link(1, 'Washington', 'place');
    $other = $s->link(2, 'Washington', 'person');
    same(3, count(array_unique([$person, $place, $other])));
    same(2, $s->count(1));
    same(1, $s->count(2));
    same($person, $s->link(1, 'washington', 'person'));
});
check('c18-subjects', 'different-titles-stay-apart', function (): void {
    $s = new App\Archive\SubjectIndex();
    $a = $s->link(1, 'Smith, John', 'person');
    $b = $s->link(1, 'Smith, Johnny', 'person');
    $c = $s->link(1, 'Smith John', 'person');
    same(3, count(array_unique([$a, $b, $c])));
});

// c19 autogen: skipped edges resolve joins, and skips propagate through steps that never run.
function flow(array $edges, int $amount): array
{
    $g = new App\Workflow\Graph();
    foreach (['submit', 'review', 'legal', 'archive', 'notify', 'audit', 'close'] as $n) {
        $g->addNode($n, function (ArrayObject $c) use ($n): void { $c['seen'] = ($c['seen'] ?? 0) + 1; });
    }
    foreach ($edges as $e) {
        $g->addEdge($e[0], $e[1], $e[2] ?? null);
    }
    return $g->run('submit', new ArrayObject(['amount' => $amount]));
}
function big(): callable { return function (ArrayObject $c): bool { return $c['amount'] > 1000; }; }
function before(array $ran, string $a, string $b): void
{
    $i = array_search($a, $ran, true);
    $j = array_search($b, $ran, true);
    if ($i === false || $j === false || $i > $j) {
        throw new RuntimeException("expected $a before $b in " . json_encode($ran));
    }
}
check('c19-workflow', 'existing', function (): void {
    same(['submit', 'review', 'notify'], flow([['submit', 'review'], ['review', 'notify']], 5));
    same(['submit'], flow([['submit', 'review', big()]], 5));
    $g = new App\Workflow\Graph();
    $g->addNode('a', function (ArrayObject $c): void { $c['ok'] = true; });
    $g->addNode('b', function (ArrayObject $c): void {});
    $g->addEdge('a', 'b', function (ArrayObject $c): bool { return isset($c['ok']); });
    same(['a', 'b'], $g->run('a', new ArrayObject()));
});
check('c19-workflow', 'skipped-edge-resolves-join', function (): void {
    $edges = [['submit', 'review', big()], ['review', 'notify'], ['submit', 'notify']];
    same(['submit', 'notify'], flow($edges, 5));
    $ran = flow($edges, 5000);
    same(3, count($ran));
    before($ran, 'review', 'notify');
});
check('c19-workflow', 'skip-propagates', function (): void {
    $edges = [['submit', 'review', big()], ['review', 'legal'], ['legal', 'notify'], ['submit', 'notify'], ['notify', 'close']];
    same(['submit', 'notify', 'close'], flow($edges, 5));
    $ran = flow($edges, 5000);
    same(5, count($ran));
    before($ran, 'legal', 'notify');
    before($ran, 'notify', 'close');
});
check('c19-workflow', 'all-skipped-means-not-run', function (): void {
    $small = function (ArrayObject $c): bool { return $c['amount'] <= 1000; };
    $edges = [['submit', 'review', big()], ['submit', 'archive', $small], ['review', 'audit'], ['archive', 'notify'], ['audit', 'notify'], ['audit', 'close']];
    same(['submit', 'archive', 'notify'], flow($edges, 5));
    $ran = flow($edges, 5000);
    same(['submit', 'review', 'audit'], array_slice($ran, 0, 3));
    same(['close', 'notify'], (function (array $r): array { sort($r); return $r; })(array_slice($ran, 3)));
});
check('c19-workflow', 'join-runs-once-after-all', function (): void {
    $edges = [['submit', 'review'], ['submit', 'legal'], ['review', 'audit'], ['audit', 'notify'], ['legal', 'notify'], ['submit', 'notify']];
    $ran = flow($edges, 5);
    same(5, count($ran));
    same(1, count(array_keys($ran, 'notify', true)));
    before($ran, 'audit', 'notify');
    before($ran, 'legal', 'notify');
});

// c20 cosh: ">>" appends, for stdout and stderr, attached or not; quoting still wins.
function sh(string $line): array
{
    return (new App\Shell\CommandLine())->parse($line);
}
check('c20-redirects', 'existing', function (): void {
    same(['argv' => ['echo', '>>', 'x'], 'stdout' => null, 'stdoutAppend' => false, 'stderr' => null, 'stderrAppend' => false], sh("echo '>>' x"));
    same(['x>>y'], sh('x\>\>y')['argv']);
    $r = sh('echo a2>f');
    same([['echo', 'a2'], 'f', false, null], [$r['argv'], $r['stdout'], $r['stdoutAppend'], $r['stderr']]);
    $r = sh('cmd 2> err > "my out"');
    same([['cmd'], 'my out', 'err'], [$r['argv'], $r['stdout'], $r['stderr']]);
    throws(App\Shell\SyntaxError::class, function (): void { sh('cmd >'); });
});
check('c20-redirects', 'append-stdout', function (): void {
    $r = sh('report --daily >> /var/log/report.log');
    same([['report', '--daily'], '/var/log/report.log', true], [$r['argv'], $r['stdout'], $r['stdoutAppend']]);
    $r = sh('a>>b');
    same([['a'], 'b', true], [$r['argv'], $r['stdout'], $r['stdoutAppend']]);
    $r = sh('echo hi 1>>f');
    same([['echo', 'hi'], 'f', true], [$r['argv'], $r['stdout'], $r['stdoutAppend']]);
});
check('c20-redirects', 'append-stderr', function (): void {
    $r = sh('cmd 2>>err.log >out');
    same([['cmd'], 'out', false, 'err.log', true], [$r['argv'], $r['stdout'], $r['stdoutAppend'], $r['stderr'], $r['stderrAppend']]);
    $r = sh('echo 2 >> f');
    same([['echo', '2'], 'f', true, null], [$r['argv'], $r['stdout'], $r['stdoutAppend'], $r['stderr']]);
});
check('c20-redirects', 'malformed', function (): void {
    throws(App\Shell\SyntaxError::class, function (): void { sh('a >>> b'); });
    throws(App\Shell\SyntaxError::class, function (): void { sh('a >> > b'); });
    throws(App\Shell\SyntaxError::class, function (): void { sh('a >>'); });
});
check('c20-redirects', 'last-redirect-wins', function (): void {
    $r = sh('a >> b > c');
    same(['c', false], [$r['stdout'], $r['stdoutAppend']]);
    $r = sh('a > b >> c');
    same(['c', true], [$r['stdout'], $r['stdoutAppend']]);
});

echo json_encode($results), PHP_EOL;
