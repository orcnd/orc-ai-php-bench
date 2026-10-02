# Trial 004: unit-level cancellations (hard, PHP 7.4)

Trial 003 plus four requirements that frontier models saturating Trial 003
(96-100 points) still have to get right. A perfect Trial 003 solution scores
69 here. The agent receives `trial4/agent/` only: a
small storefront module (cart, promotions, checkout snapshots, cancellations,
ledger) with one visible failing ticket test (FIN-2291) and a finance spec in
`docs/REFUNDS.md`. The code diverges from the spec in ten places; the ticket
reveals only two of them.

Planted defects (the agent is told only "several places diverge"):

| Area | Defect |
| --- | --- |
| discount | promotion base includes return credits; fixed promotions not capped by merchandise |
| allocation | per-line `round()` does not sum to the discount; ties not resolved in line order |
| partial | shipping refunded; credits applied per request (negative refunds); unknown line ids mutate state before throwing; zero refunds written to the ledger |
| snapshot | snapshot aliases mutable `CartLine` and `Promotion` objects; cancellation re-prices from the live catalogue (edited or retired campaigns) |
| idempotency | idempotency keys are global although the spec scopes them per order |
| units | unit-level cancellation (`['sku' => 2]`, mixable with bare ids), cumulative floor rounding, over-cancel rejection |
| idempotency | month-end ledger close: a failed ledger write must leave no state, the same key must succeed after reopen |
| snapshot | migrated v2 orders carry stored allocations that must be honoured, never recomputed |
| wholesale | 5·10^9-cent orders: `discount * lineTotal` overflows int64; allocation must stay exact (no floats, no TypeError) |

Traps: PHP 7.4 production runtime (no `match`, promoted constructors,
`str_contains`, union types, `mixed`, nullsafe operator...), suspicious-looking
but protected code owned by other teams (account module, receipt formatter,
report) and a public API that hidden tests depend on (`App\Kernel`).

## Scoring (`schema.json`, total 100)

public 5, discount 10, allocation 10, partial 10, units 10, snapshot 10,
idempotency 10, wholesale 5, scope 10, runtime 5, phpstan 5, tests 10.

* Feature groups: credit only for hidden tests that fail on the pristine
  baseline; capped at half weight below 50% raw pass rate. One test is a
  60-order randomised property check (two: line and unit partitions) (partitions must sum to the full refund).
* scope: behaviour tests plus unchanged hashes of protected files.
* runtime: PHP 7.4 lint of every file, visible tests on 7.4 and identical
  hidden results on 7.4.
* phpstan: level max, phpVersion 70400, -1 per newly introduced error.
* tests: the agent's `tests/run.php` is run against the reference solution
  (must pass) and 19 reference mutants. Credit = mutants killed that the
  pristine visible tests did not already kill.
* scope/runtime/phpstan/tests need at least one new core test passing.

## Commands

```text
python trial4/runner.py prepare --workspace /outside/repo/ws
python trial4/runner.py score --workspace /outside/repo/ws --phpstan vendor/bin/phpstan.phar --runtime-php /path/to/php7.4
python trial4/runner.py selftest --phpstan vendor/bin/phpstan.phar --runtime-php /path/to/php7.4
python trial4/agents.py --out /outside/repo/runs --runs 2 claude:haiku claude:sonnet opencode:provider/model
```

Selftest asserts: no-change scores 0, the visible test fails on baseline, the
reference plus exemplary tests (`selftest_tests.php`) scores 100, every mutant
lowers the score, and a trace touching evaluator files invalidates the run.
