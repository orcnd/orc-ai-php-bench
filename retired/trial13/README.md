# Trial 013: localisation maze

Vague report "migrated wholesale refunds are a cent short". About 14 plausible files (calculator with a tempting TODO, deprecated legacy service, banker's rounding helper, listener, price list, invoice reprinter, revenue report). Root cause: one line in `LegacyOrderHydrator` (line total rebuilt as `intdiv(total, qty) * qty`). Hidden checks: refunds, invoice reprint and revenue report for 40 random legacy orders; nothing else changes; the `minimal` group gives full credit only if the root-cause file is changed, minus 25% per other changed `app/` file and for more than 6 changed lines. Groups: fix 45, regression 25, minimal 20, runtime 5, phpstan 5.

Commands: `python3 trial13/runner.py prepare|score|selftest --workspace ... --runtime-php <php7.4> --phpstan vendor/bin/phpstan.phar`.
