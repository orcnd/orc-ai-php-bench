# Trial 011: rolling upgrade, V1 and V2 side by side

V1 (frozen, `legacy/V1`, protected) and the candidate V2 share a FileStore and an invoice queue. Hidden checks: V2 reads V1 documents (address parsing, float fees to cents with 0.29/1.15 traps); V1 reads V2 documents; a V1 address change (which drops unknown fields) must not lose V2 data (currency) and must be picked up by V2; messages flow V1->V2 and V2->V1 (V1 rejects unknown types and needs `fee`); redelivery is a no-op; the migration is idempotent, survives being killed after any write (`STORE_CRASH_AT`) and copes with V1 writes during migration. Changing `legacy/` or `app/Storage` invalidates the run. Groups: compat 30, messages 25, migration 35, runtime 5, phpstan 5.

Commands: `python3 trial11/runner.py prepare|score|selftest --workspace ... --runtime-php <php7.4> --phpstan vendor/bin/phpstan.phar`.
