# Active score policy

Authoritative weights: trial2/schema.json (total 100). Legacy score tables and
root smoke tests are retired. Public 10, pricing 20, snapshot 20, idempotency 15,
scope 10, target runtime 10, PHPStan 10, context 5.

For public, pricing, snapshot and retry, credit counts tests that pass on the
candidate but fail on the pristine baseline. Already-passing tests give no new
feature credit. With fewer than half raw tests passing, category credit is
capped at half its weight. Scope and tooling credit require at least one new
core test to pass. Thus a no-change candidate scores zero even with tooling.

Scope requires protected account files to retain their hashes and contracts.
PHPStan uses level max, phpVersion 70400 and error fingerprints including file,
identifier and message with multiplicity. Fixing an old error cannot cancel
an unrelated introduced error. Line-number movement is not a new error.

Runtime requires actual PHP 7.4 lint and executable hidden harness. PHPStan
is not a substitute for runtime validation. Missing tools produce not_measured.
Context uses runner-supplied tool-return tokens, not actual model occupancy.
Trace evidence of evaluator/reference reads invalidates the run (exit 2).
Empty traces receive no credit. OS-level agent isolation is required; traces
are diagnostic and cannot prove that hidden material was never accessed.

Selftest verifies no-change score zero, a failing visible baseline, a correct
reference solution and a retry-guard mutation losing credit. It does not
calibrate models. Provider token/context accounting and three-version projects
remain outstanding.
