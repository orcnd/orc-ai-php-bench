# FIN-4120: splits do not reconcile

Finance's nightly reconciliation flags cent differences in refund splits,
mid-period plan changes and instalment plans. All of them go through
`App\Money\Allocation`. Make it satisfy `docs/ALLOCATION.md` for all inputs.

Keep the public API. PHP 7.4 (64-bit), no dependencies. `php tests/run.php`
must pass; add tests.
