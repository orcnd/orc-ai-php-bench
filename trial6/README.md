# Trial 006: crash consistency (PHP 7.4)

Trial 005 (persistence, multi-process concurrency, ADR precedence, Berlin
withdrawal period, outbox exactly-once) plus crash consistency: the platform
chaos hook `STORE_CRASH_AT=n` kills the worker after its n-th store write,
and the CRM outbox has maintenance windows. Ledger, outbox and cancellation
state share no transaction, so the solution needs an intent record and
recovery from `Ledger::entries()`, plus idempotent e-mail catch-up.
Trial 005's best solutions score 0-4/8 on the crash group.

| Research finding | Trial 005 mechanism |
| --- | --- |
| Implicit requirements are the largest single cause of agent failure: 24.5-46% of failures in SWE-RPG | TASK.md only says "bring cancellations in line with `docs/`". Persistence, locking, the e-mail contract and the delivery rule must be recovered from `docs/OPERATIONS.md`, `docs/adr/` and `public/index.php` |
| Agents trust plausible documentation and comments that defend a defect (Terminal-Bench 3.0 deep dives) | `docs/REFUNDS.md` and a code comment still say delivery is never refundable; Accepted ADR 0011 overrides both (precedence is in `docs/README.md`). ADR 0012 is only Proposed and must not be implemented |
| Bugs that live in component interaction over time, not in a single function (Terminal-Bench 3.0) | Every request is a new PHP-FPM process: state kept in PHP memory is lost (hidden tests restart the `Kernel` between requests). Ledger, outbox and cancellation state must commit together |
| Concurrency is a weak spot (CONCUR) | Up to 6 real PHP processes hit the same order at once through a shared `FileStore` with simulated NFS latency; only `FileStore::transaction()` (flock) prevents double refunds and duplicate e-mails |
| Subtle edge cases and runtime behaviour (SWE Atlas) | The withdrawal period ends at midnight in Europe/Berlin, with CET/CEST changes; checks are exact to the hour around both |
| Agents stop at the first plausible fix (Terminal-Bench 3.0) | About 20 coupled defects. The visible test exposes one |

Score groups (`schema.json`): public 2, pricing 5, refunds 8, idempotency 5,
persistence 5, concurrency 10, withdrawal 10, outbox 5, crash 25, scope 5,
runtime 5, phpstan 5, tests 10. There are 29 mutants. The selftest's
exemplary tests kill all of them, including a lock-removal mutant, by
spawning workers.

Every hidden requirement can be traced to a file in the workspace:

| Hidden group | Evidence in the workspace |
| --- | --- |
| persistence | `docs/OPERATIONS.md` (Runtime) and `public/index.php`: a fresh `Kernel` per request |
| concurrency | `docs/OPERATIONS.md` (retries while the first attempt is still running, double clicks) and the `FileStore` docblock |
| outbox | `docs/OPERATIONS.md` (Customer e-mails) and the `Outbox` docblock |
| withdrawal | `docs/README.md` (precedence), ADR 0011 and the ADR index (0012 is Proposed) |

The hidden suite needs `proc_open` and runs 25 extra PHP processes. On a
laptop it takes about 10 seconds.
