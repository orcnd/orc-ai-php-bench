# FIN-3301: some migrated wholesale refunds are a cent short

Finance: "Some refunds for migrated wholesale orders are 1 cent short (for
example W-88812, see tests/fixtures/orders.php). Happens since the ERP
migration. Please fix."

Find the cause and fix it. Keep the change as small as the cause allows;
this goes into a hotfix release and every changed file needs a second
review. Keep public APIs. PHP 7.4. `php tests/run.php` must pass; add a
regression test.
