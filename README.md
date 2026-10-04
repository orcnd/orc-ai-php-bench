# ORC PHP benchmark

Active trials: 002, 004, 005, 006, 011, 012, 015, 016 (see RESULTS.md,
"Active benchmark after round 2"). Saturated trials (003, 007, 008, 009, 010, 013, 014) were
moved to `retired/`; their runners still work from there.

Issue triage with by-design traps: Trial 007-010 (`trial7/` to `trial10/`).
System-level trials 011-014: rolling upgrade (`trial11/`), event ordering
(`trial12/`), bug localisation with minimal-patch scoring (`trial13/`) and
property/metamorphic testing (`trial14/`); shared schema-driven `runner.py`.
Hardest engineering: Trial 006 (`trial6/`, crash consistency) and Trial 005 (`trial5/`,
persistence, concurrency, ADR precedence). Before those, Trial 004 (`trial4/`, see `trial4/README.md`):
unit-level cancellations with planted spec divergences, month-end ledger
atomicity, migrated orders, int64-overflow-sized wholesale orders, PHP 7.4
runtime checks and mutation scoring of the agent's own regression tests.
Trial 003 (`trial3/`) is the same task without the four hard requirements;
Trial 002 below is the original, easiest task. Model results: `RESULTS.md`.

The runnable benchmark was Trial 002. The root app and old PHP suites are
legacy smoke fixtures and are excluded from the active score.

Prepare a new workspace outside this repository:

```text
python trial2/runner.py prepare --workspace /path/to/new-agent-workspace
python trial2/runner.py score --workspace /path/to/agent-workspace --phpstan vendor/bin/phpstan
python trial2/runner.py selftest --phpstan vendor/bin/phpstan
```

These commands work on Windows, Linux and macOS with Python and PHP on PATH.
Install evaluator dependencies with `composer install`. PHPStan is pinned by
composer.lock. Target runtime validation additionally requires `--runtime-php`
pointing to a PHP 7.4 executable. Missing measurements are provisional.

Never give an agent this development repository. Export the evaluator with
`python trial2/runner.py export-evaluator --workspace /path/to/new-private-evaluator`.
Keep that directory private (optionally initialise its own Git repository).
Run the agent with OS/container filesystem access restricted to the prepared
workspace. Copying files alone is not an access-control boundary.

The single authoritative score schema is `trial2/schema.json`.
See `evaluator/SCORING.md` for the implemented policy and limitations.
