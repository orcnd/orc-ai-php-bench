# Trial 014: metamorphic / property testing

`Allocation::allocate`, `prorate` and `installments` are checked only through properties on seeded random inputs (no fixed answers): exact sum, within one cent of the proportional value, sign, zero weights, independence of key order, scale invariance, totals up to 10^12 with weights up to 10^9 (int64 overflow), all-zero weights; proration additivity over random cut points, full/empty periods, bounds, leap years and DST days under Europe/Berlin; instalment sum, spread and order. Groups: allocate 50, prorate 30, installments 10, runtime 5, phpstan 5.

Commands: `python3 trial14/runner.py prepare|score|selftest --workspace ... --runtime-php <php7.4> --phpstan vendor/bin/phpstan.phar`.
