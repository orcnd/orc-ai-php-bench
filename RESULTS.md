# Model results (2026-10-03)

Agents: Claude Code subagents (general-purpose tool set) with a model
override, one isolated workspace per run outside this repository, identical
prompt ("read TASK.md and complete the task, work only inside this
directory"). Scored with `--phpstan vendor/bin/phpstan.phar` and
`--runtime-php` PHP 7.4.33, so every score is fully measured (Trial 002's
`context` group was not measured: 0/5 for everyone). Transcripts were checked
for evaluator paths; no run read evaluator material.

| Model | T002 | T003 | T004 | T005 | T006 | T007 | T008 | T009 | T010 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Fable 5.1 | 84.6 | 100.0 / 97.0 | 98.8 / 96.4 | 98.6 / 96.1 | 97.5 / 93.4 | 100 / 100 | 100 / 100 | 100 / 100 | 99.5 / 98.4 |
| Opus 5.5 | 89.0 | 99.1 / 96.6 | 99.4 / 95.8 | 99.1 / 99.1 | 93.5 / 95.0 | 100 / 100 | 100 / 100 | 100 / 99.5 | 98.2 / 99.8 |
| Sonnet 5.5 | 84.6 | 96.1 / 96.1 | 92.4 / 93.0 | 94.4 / 92.1 | 91.3 | 100 | 95.1 | 90.8 | 98.8 |
| Haiku 4.5 | 51.6 | 73.3 / 68.4 | 73.0 / 74.6 | 36.0 | 33.3 | 49.8 | 42.9 | 45.9 | 45.8 |

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

Trial 010 (T009 plus 4 deeper GitHub-sourced fences: Sylius per-unit tax,
WooCommerce per-line rounding, BGB §366 / moneyphp allocation order,
brick/money RoundingNecessary; a shared `Rounding` helper where the real
bug #123 tempts a wholesale rewrite; the invented acronym ADRESS that looks
like a typo plus a "fix the typo" ticket; two e-mail validators, regex-only
`EmailSyntax` and regex + DNS `DeliverableEmail`, to be chosen for signup
and for a 150,000-row CSV import with a 30 s budget)
* Fable, Opus and Sonnet: every bug, every fence (10/10 tickets declined
  with `var/` evidence), ADRESS kept, `DeliverableEmail` at signup,
  `EmailSyntax` in the import (0 DNS calls, 150k rows < 1 s), file archived.
  Only losses: one PHPStan error in some imports and a few surviving
  mutants (no test for the archive step or the signup DNS check).
* Haiku: renamed ADRESS to AddressArchive, used regex only at signup,
  broke 8 of 14 fence checks and the import.

## Trials 011-014 (one run per model)

| Model | T011 rolling upgrade | T012 event ordering | T013 localization | T014 properties |
| --- | --- | --- | --- | --- |
| Fable 5.1 | 86.7 | 57.5 | 100 | 100 |
| Opus 5.5 | 86.7 | 95.0 | 100 | 100 |
| Sonnet 5.5 | 91.7 | 95.0 | 100 | 100 |
| Haiku 4.5 | 0.0 | 17.0 | 100 | 50.0 |

* T011: all three frontier models found the sidecar / dual-format design
  (V1 fields always written, V2-only data in a separate document, stale
  detection, `invoice.requested` with legacy `fee`, currency fallback for
  V1-written invoices, idempotent and crash-safe migration). All three
  failed `redelivery-is-harmless`: a redelivered V1 message after the
  customer's currency changed rewrites the invoice with the new currency
  (they "overwrite the same invoice" instead of first-write-wins). Haiku
  kept the V2-only format and the `.v2` message type: every check failed.
* T012: Opus and Sonnet built an order-independent fact store. Fable added
  its own rule ("a completed refund suppresses IssueRefund") that is not
  in the spec, which makes commands depend on arrival order: it failed 3 of
  6 histories under shuffling/duplication. Haiku: in-order only.
* T013: every model, Haiku included, fixed the one-line root cause in
  `LegacyOrderHydrator` and touched nothing else (2-4 changed lines). The
  maze was not hard enough: the bug report plus a 1-cent example points
  straight at integer division.
* T014: Fable, Opus, Sonnet satisfied every property on 12,000+ random
  cases (overflow-safe integer maths, key tie-breaks, cumulative proration).
  Haiku used float maths and per-range floor: failed sum/bounds at 10^12,
  additivity and the DST case.
* PHPStan level max is where frontier runs lose points in T011/T012
  (0/5 for most: untyped `mixed` arrays).

## Trial 015: maintainer review (patterns from rejected AI PRs)

Built from `research/research_rejected_prs_*.md` (AIDev-based studies: failing
CI 17-18% and incorrect/incomplete fixes 15% of rejections; 23 concrete
rejected agent PRs incl. WooCommerce #66653/#66211, symfony #66279). Five
issues, each modelled on one pattern: filter-override bypass, cache keyed by
method only, "fix only the named gateways", DST/timezone via Clock, CSV
escaping beyond quotes. Scored like a maintainer: functional 25,
completeness 20, invariants 15, CI gate (`bin/ci`: PHP 7.4 lint, PHPStan max,
tests; CI files protected) 15, CONTRIBUTING conventions 10, mutation score
of the PR's tests 10, discipline/scope 5.

| Model | Score | Lost on |
| --- | --- | --- |
| Fable 5.1 | 97.3 | tests (3 surviving mutants) |
| Opus 5.5 | 92.4 / 95.5 | run 1 deliberately left SEPA/GiftCard unfixed ("issue names only three, keep PR focused"); weak CSV tests |
| Sonnet 5.5 | 92.7 | tests (8 surviving mutants) |
| Haiku 4.5 | 78.0 | SEPA/GiftCard, cache invalidation, filter bypass for tax, no useful tests |

Every model ran `bin/ci` and got it green, followed every convention and
left existing tests untouched. The rejection patterns that dominate real
agent PRs (red CI, convention violations, test tampering) did not appear
once the CI script and rules were in the repository.

## Trial 016: rejected agent PRs (AIDev) ported to PHP

15 cases, one per rejected Copilot/Devin PR from `research/REJECTED_AI_PRS.md`
(phiki #76, azure-service-operator #4864, camunda #37452, msbuild #12591,
Homebrew #20658, infrahub #6767, vscode #268211, ArcadeDB #2587,
fromthepage #4811, django-upgrade #598, marimo #3806, opteryx #2860,
aspnetcore #62623, lakasir #328, Cloudlog #3335). Each issue is phrased like
the original report; hidden checks encode the maintainer's objection. A case
that breaks behaviour which already worked scores 0 (regression rule).

| Model | Score | Cases lost |
| --- | --- | --- |
| Fable 5.1 | 100 | none |
| Opus 5.5 | 93.0 / 88.0 | run 2: #10 left the second `discount()` in an expression unconverted; PHPStan |
| Sonnet 5.5 | 93.0 | #10 (same bug as Opus run 2); PHPStan |
| Haiku 4.5 | 64.0 | #3 redelivery, #5 XDG overrides the existing legacy file, #6 hard error breaks existing schemas, #10, #14 restore conflict, #15 fixed only the 3 named files |

Haiku reproduced the original agents' failures (fixed only named files,
broke compatibility, ignored redelivery); the frontier models avoided
almost all of them. Two checks were relaxed after the runs because the
docs allowed two readings (XDG default path when nothing is configured;
whether to rewrite mixed/nested `discount()` calls).

## Totals (all 83 runs rescored with the final graders, 2026-10-04)

Mean per trial (runs in brackets), 100 points each.

| Trial | Fable 5.1 | Opus 5.5 | Sonnet 5.5 | Haiku 4.5 |
| --- | --- | --- | --- | --- |
| T002 cancellation (original) | 84.6 | 89.0 | 84.6 | 51.6 |
| T003 partial cancellation | 98.5 (100/97) | 97.8 (99.1/96.5) | 96.1 (96.1/96.1) | 70.9 (73.3/68.4) |
| T004 units, wholesale, ledger close | 97.6 (98.8/96.4) | 97.6 (99.4/95.8) | 92.7 (92.4/93.0) | 73.8 (73.0/74.6) |
| T005 persistence, concurrency, ADR | 97.3 (98.6/96.1) | 99.1 (99.1/99.1) | 93.2 (94.4/92.1) | 37.3 |
| T006 crash consistency | 95.5 (97.5/93.4) | 94.3 (93.5/95.0) | 91.3 | 35.5 |
| T007 triage, fences (ADRs) | 100 | 100 | 100 | 49.8 |
| T008 fences, indirect evidence | 100 | 100 | 95.1 | 42.9 |
| T009 fences, data-only evidence | 100 | 99.8 (100/99.5) | 90.8 | 45.9 |
| T010 deeper fences, ADRESS, validators | 98.9 (99.5/98.3) | 99.0 (98.2/99.8) | 98.8 | 45.8 |
| T011 rolling upgrade | 86.7 | 86.7 | 91.7 | 0.0 |
| T012 event ordering | 57.5 | 95.0 | 95.0 | 17.0 |
| T013 localisation maze | 100 | 100 | 100 | 100 |
| T014 property testing | 100 | 100 | 100 | 50.0 |
| T015 maintainer review | 97.3 | 93.9 (92.4/95.5) | 92.7 | 78.0 |
| T016 rejected AI PRs | 100 | 90.5 (93.0/88.0) | 93.0 | 64.0 |
| **Average (15 trials)** | **94.3** | **96.2** | **94.3** | **50.8** |
| **Total (of 1500)** | **1413.9** | **1442.7** | **1415.0** | **762.2** |
| Lowest trial | 57.5 (T012) | 86.7 (T011) | 84.6 (T002) | 0.0 (T011) |

## Active benchmark (saturated trials retired, 2026-10-04)

Retired to `retired/` because Fable, Opus and Sonnet all averaged >= 95:
T003, T007, T008, T010, T013 (Haiku also 100), T014; T009 retired on request
(Fable 100, Opus 99.8, Sonnet 90.8). Their results stay in
the tables above for reference.

| Trial | Fable 5.1 | Opus 5.5 | Sonnet 5.5 | Haiku 4.5 |
| --- | --- | --- | --- | --- |
| T002 cancellation (original) | 84.6 | 89.0 | 84.6 | 51.6 |
| T004 units, wholesale, ledger close | 97.6 | 97.6 | 92.7 | 73.8 |
| T005 persistence, concurrency, ADR | 97.3 | 99.1 | 93.2 | 37.3 |
| T006 crash consistency | 95.5 | 94.3 | 91.3 | 35.5 |
| T011 rolling upgrade | 86.7 | 86.7 | 91.7 | 0.0 |
| T012 event ordering | 57.5 | 95.0 | 95.0 | 17.0 |
| T015 maintainer review | 97.3 | 93.9 | 92.7 | 78.0 |
| T016 rejected AI PRs | 100 | 90.5 | 93.0 | 64.0 |
| **Average (8 trials)** | **89.6** | **93.3** | **91.8** | **44.6** |
| **Total (of 800)** | **716.5** | **746.1** | **734.2** | **357.2** |

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

## Hardening round 2 (2026-10-04)

Changes: T009 retired. T011 gained a lossless rule (a V2 save that does not
change the address or fee keeps the V1 bytes) and the `lossless` group.
T012 gained three histories and a 20,000-event hot-order volume check
(< 2 s), documented in `docs/EVENTS.md`. T016 went from 15 to 20 cases
(RPKI maxLength, Spark `ignore` save mode, Unicode subject dedupe,
conditional workflow edges, `>>` redirects; 4.5 points each). T004 gained a
randomised mixed-operations property test (close/reopen, replays,
over-cancels, unknown lines, keys shared across orders). A double-crash
test for T006 was dropped: every earlier solution passed it and it added
minutes to the suite.

T011, T012 and T016 were rerun once per model (ws84-ws95); T004 was
rescored (only Haiku's runs fail the new property test).

| Run | Fable 5.1 | Opus 5.5 | Sonnet 5.5 | Haiku 4.5 |
| --- | --- | --- | --- | --- |
| T011 | 85.7: V1 message to a V2 worker billed in EUR instead of the customer's currency; PHPStan | 96.0: PHPStan | 61.8: only 5-digit postcodes parsed (Swiss address left unparsed), migration not idempotent, redelivery; PHPStan | 17.0 |
| T012 | 95.0: PHPStan only; volume passed | 95.0: PHPStan only | 95.0: PHPStan only | 12.0 |
| T016 | 87.8: c16 AS0 + malformed ROA, c17 ignore validates the data first + mode names, c20 last redirect wins, own test uses a PHP 7.4-deprecated API (runtime 0) | 91.1: c16 malformed ROA, c17 ignore validates the data first, runtime 0 (same 7.4 deprecation) | 97.0: c16 malformed ROA, c17 | 37.4 |

### Active benchmark after round 2

| Trial | Fable 5.1 | Opus 5.5 | Sonnet 5.5 | Haiku 4.5 |
| --- | --- | --- | --- | --- |
| T002 cancellation (original) | 84.6 | 89.0 | 84.6 | 51.6 |
| T004 units, wholesale, ledger close | 97.6 | 97.6 | 92.7 | 72.7 |
| T005 persistence, concurrency, ADR | 97.3 | 99.1 | 93.2 | 37.3 |
| T006 crash consistency | 95.5 | 94.3 | 91.3 | 35.5 |
| T011 rolling upgrade (rerun) | 85.7 | 96.0 | 61.8 | 17.0 |
| T012 event ordering (rerun) | 95.0 | 95.0 | 95.0 | 12.0 |
| T015 maintainer review | 97.3 | 93.9 | 92.7 | 78.0 |
| T016 rejected AI PRs (rerun, 20 cases) | 87.8 | 91.1 | 97.0 | 37.4 |
| **Average (8 trials)** | **92.6** | **94.5** | **88.5** | **42.7** |
| **Total (of 800)** | **740.8** | **756.0** | **708.3** | **341.5** |

Once a requirement is written down in the workspace (volume budget,
lossless rule), the frontier models meet it; the hardening mostly moved
points within the ranking, not the ceiling. What still costs them points:
PHPStan level max (every T012 rerun lost all 5), unstated domain knowledge
(RFC 6482 malformed ROAs, Spark's "ignore" not reading the data), PHP 7.4
runtime details in their own tests, and single-run variance (Sonnet's T011
dropped from 91.7 to 61.8).
