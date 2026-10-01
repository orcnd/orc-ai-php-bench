# Evaluator policy

The agent receives only `app/`, `tests/`, the public task prompt, and a
sanitised Composer manifest. `evaluator/` is mounted only after the run.

The evaluator runs the suite in a hidden PHP 7.4 image and PHPStan 1.12 with
`phpVersion: 70400`. New PHPStan errors are calculated against the baseline.
PHPStan is not the syntax authority: the hidden PHP runtime rejects unsupported
syntax before tests run.

Scoring is 100 points: public 10, hidden business invariants 25, snapshot and
retry regression 15, scope 10, hidden runtime 15, PHPStan delta 15, context
telemetry 10. Each group contains at least four cases and gives at most half
credit unless half of its cases pass.
