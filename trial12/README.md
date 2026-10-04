# Trial 012: event ordering

`OrderProjector` receives payment events at least once, in any order. Six realistic histories (partial and full refunds, timeout then late authorisation, split captures, cancellation with late authorisation, retry after timeout) are replayed in order, reversed, shuffled 40 times and with random duplicates, and interleaved across orders; state and the set of PSP commands (IssueRefund, VoidAuthorization, each exactly once, refunds held until capture) must always be identical. Groups: inorder 15, shuffled 35, duplicated 30, interleaved 10, runtime 5, phpstan 5.

Commands: `python3 trial12/runner.py prepare|score|selftest --workspace ... --runtime-php <php7.4> --phpstan vendor/bin/phpstan.phar`.
