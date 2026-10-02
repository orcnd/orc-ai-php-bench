# Implemented hardening

Run `powershell -File evaluator/score.ps1`. There are 29 independent cases:
12 pricing cases, 8 rounding/partition cases, 9 response and credit-isolation
regressions. Each case is scored proportionally; a failed case does not stop
later checks. PHPStan runs at level max against the checked-in dependency lock.

Pass `-RuntimePhp <path-to-php-7.4>` for actual target-runtime lint and execution.
Without this executable the runtime category is unmeasured, not passed.
Pass a runner-produced JSONL trace through `-TracePath` for tool-token scoring.
An empty or absent trace is unmeasured. This trace does not measure provider
reasoning tokens or actual context occupancy.

An observed artifact score is not a fresh model run. Existing solutions must
be restored to their task baseline in independent workspaces before model
calibration. Hidden evaluator code must stay outside those workspaces.

Full scores remain possible when every contract is satisfied. Static analysis
has no deliberate error requirement: valid, correctly typed code earns credit.
The current repository's three version-specific case directories are still
scaffolds, not executable tasks. No claim about current-model success rates is
supported until independent runs have completed.
