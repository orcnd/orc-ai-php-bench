# ORC PHP benchmark

The runnable benchmark is Trial 002. The root app and old PHP suites are
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
