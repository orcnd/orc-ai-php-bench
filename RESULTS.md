# Model results (2026-10-03)

Agents: Claude Code subagents (general-purpose tool set) with a model
override, one isolated workspace per run outside this repository, identical
prompt ("read TASK.md and complete the task, work only inside this
directory"). Scored with `--phpstan vendor/bin/phpstan.phar` and
`--runtime-php` PHP 7.4.33, so every score is fully measured (Trial 002's
`context` group was not measured: 0/5 for everyone). Transcripts were checked
for evaluator paths; no run read evaluator material.

| Model | T002 | T003 | T004 | T005 | T006 | T007 | T008 | T009 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Fable 5.1 | 84.6 | 100.0 / 97.0 | 98.8 / 96.4 | 98.6 / 96.1 | 97.5 / 93.4 | 100 / 100 | 100 / 100 | 100 / 100 |
| Opus 5.5 | 89.0 | 99.1 / 96.6 | 99.4 / 95.8 | 99.1 / 99.1 | 93.5 / 95.0 | 100 / 100 | 100 / 100 | 100 / 99.5 |
| Sonnet 5.5 | 84.6 | 96.1 / 96.1 | 92.4 / 93.0 | 94.4 / 92.1 | 91.3 | 100 | 95.1 | 90.8 |
| Haiku 4.5 | 51.6 | 73.3 / 68.4 | 73.0 / 74.6 | 36.0 | 33.3 | 49.8 | 42.9 | 45.9 |

Trial 002 ran once per model; Trials 003 and 004 ran twice; Trials 005 and
006 ran twice for Fable and Opus (Sonnet twice on Trial 005), once otherwise.

Calibration: no-change control 0 and reference 100 on all trials; the
perfect Trial 003 solution scores 69.0 on Trial 004.

## Where points were lost

Trial 002
* Every model lost 4.4 pricing points on `money-4` / `money-5`: the hidden
  suite expects promotions of 150% and -20% to be clamped to 0-100, which
  TASK.md never states. This is a spec gap in Trial 002, not a model error.
* Haiku did not implement snapshot reuse or retry idempotency (0/20, 0/15).
* All models introduced PHPStan errors (`float|int` leaking into int-typed
  arrays).

Trial 003
* Fable, Opus and Sonnet passed every hidden behaviour test; the only
  differences are PHPStan hygiene (unused constructor dependencies left in
  `CancellationService`) and mutants their regression tests missed.
* Haiku: no snapshot cloning (basket/campaign edits leak into placed
  orders), global idempotency keys, and in one run no largest-remainder
  allocation. Its tests killed 1-2 of 11 available mutants.

Trial 004
* Fable and Opus: all hidden behaviour tests pass in all four runs,
  including int64-overflow-safe allocation (all wrote a hand-rolled
  `mulDiv`), month-end atomicity and unit-level rounding. Scores differ only
  on PHPStan and tests (the overflow mutant survived in 3 of 4 runs: they
  test wholesale refunds but not exact allocations).
* Sonnet: both runs fail `basket-reprice`: they cloned in the repository
  (`save`/`find`) but `CheckoutService::place()` still returns a snapshot
  that aliases the live cart. Weaker regression tests (4-5 survivors).
* Haiku: no snapshot isolation, global idempotency keys, a bare id after a
  partial unit cancellation, wholesale unit rounding, PHPStan errors; one
  run's tests failed on the reference, so it received no test credit.

Trial 005 (persistence, real multi-process concurrency, ADR precedence,
Berlin/DST withdrawal period, outbox exactly-once; mostly implicit in docs)
* Fable, Opus and Sonnet recovered every implicit requirement from
  `docs/OPERATIONS.md`, `docs/adr/` and `public/index.php`: state moved to
  the FileStore, `FileStore::transaction()` locking, ADR 0011 applied in
  Europe/Berlin, ADR 0012 (Proposed) correctly ignored. Remaining losses:
  PHPStan and mutants (lock removal survives most suites).
* Haiku missed persistence, concurrency and withdrawal entirely (0 points).

Trial 006 (crash consistency + outbox outages)
* Without the crash documentation, Trial 005's best solutions score 0-4/8 on
  the crash group. With it, all Fable/Opus/Sonnet runs implemented an
  intent record + ledger-based recovery + idempotent e-mail catch-up and
  passed every crash test. Losses are almost entirely PHPStan level max
  (0/5 in four of five runs: untyped state arrays) and mutation score.
* Scoring fix found here: one Fable run's tests pinned a different, valid
  recovery design and failed on the reference. Tests that fail on the
  reference are now ignored individually instead of voiding the suite
  (Trials 005/006 runners).

Trial 007 (issue triage: 12 real bugs ported from GitHub issues of
Kimai, WooCommerce, Invoice Ninja, Firefly III, Sylius; 6 tickets that
describe intended behaviour closed "by design" by real maintainers, with
evidence in ADRs and code comments)
* Fable, Opus and Sonnet fixed all 12 bugs, both code paths of the
  two-path bugs, the PHP 7.4-only `0 == ''` and unstable-`usort` bugs, and
  declined all 6 by-design tickets with references to the ADRs.
* Haiku "fixed" every by-design ticket (fences 8.75/35) and missed 3 bugs.

Trial 008 (same, but explanatory comments and ADRs removed; evidence only
in CHANGELOG, closed issues and consumer code; by-design tickets marked
"approved" by product owner / CFO; wrong "not a bug" comments on two real
bugs)
* Fable and Opus: 100 in all four runs. They found the evidence in the
  changelog, closed issues and `XRechnungWriter`/`AuditExport`, overruled
  the approvals and flagged them for the decision-makers, and saw through
  both misleading "cannot reproduce on PHP 8" comments.
* Sonnet followed one approval (#105 half-down VAT) against closed issue
  #64; kept the other five.
* Haiku broke 7 of 8 fences and followed the "caching issue" comment.

Trial 009 (same, but the only evidence is raw production data in `var/`:
portal rejection/acceptance XML, till VAT export, ERP responses, payroll
provider responses, tax-audit findings; CHANGELOG entries, closed issues
and the e-invoice validator check removed)
* Fable and Opus: still recovered all six fences from the data, citing the
  rejected invoice INV-1187, the 211-line till reconciliation, the ERP
  rejection and the audit findings.
* Sonnet: kept five; for #101 it found a creative alternative (bill exact
  minutes and widen the quantity to 6 decimals so BR-CO-10 still holds).
  The hidden test counts this as a broken fence; it is arguably valid and
  should be reviewed if T009 is used for ranking.
* Haiku: broke 7 of 8 fences and missed 6 bug checks.

## Reading the numbers

* Two runs per model are enough to rank Haiku vs. the rest, not to
  separate Fable, Opus and Sonnet: their run-to-run spread (3-4 points) is
  as large as the gaps between them.
* Neither escalation route (harder engineering in T004-T006, human-style
  bugs and Chesterton's fences in T007-T008) brought the best model near 60. Whenever a
  requirement is discoverable in the workspace (spec, ops doc, ADR,
  chaos-hook docblock), Fable and Opus implement it correctly, including
  int64-exact arithmetic, multi-process locking and crash recovery. What
  they still miss is undocumented behaviour (Trial 005 solutions vs. Trial
  006 crash tests) and code hygiene (PHPStan level max, mutation coverage).
* Haiku drops sharply as soon as requirements are implicit (73 -> 36) and
  is the only model that falls for the fences (T007 49.8, T008 42.9): the
  fence design separates small from frontier models, not frontier models
  from each other.
* Research behind T007/T008 is in `research/` (27 GitHub bugs, 18 by-design
  cases, 16 AI failure modes with sources).
* Harness note: Claude Code attaches diffs of files edited in the parent
  session to subagent tool results. Two transcripts contained such diffs of
  benchmark files edited while the run was in progress; neither run's
  result was affected, and no benchmark file was edited during T008 runs.
* Haiku passed the 50M EUR allocation test in both Trial 004 runs without an
  exact `mulDiv` (float arithmetic happened to round correctly for those
  inputs); the `wholesale` group would benefit from adversarial inputs.
* Only Claude models were run: in this environment the Gemini API key was
  rejected, OpenRouter credit was too low, the opencode free models require
  opencode >= 1.18 (installed 1.14.46) and Ollama has no local models.
  `trial3/agents.py` (or `trial4/agents.py`) runs `claude` / `opencode` CLIs
  headless when those are available.
