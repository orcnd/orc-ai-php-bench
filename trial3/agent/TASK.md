# FIN-2291: wrong refunds on cancelled orders

Finance escalated several incorrect refunds. The reported case: an order
with a 20% promotion, a 10.00 EUR item, a 5.00 EUR return credit and 5.00
EUR shipping was cancelled and refunded 9.00 EUR instead of 3.00 EUR.
Audit also found refunds that differ from what finance computes by hand
for partial cancellations, for retried requests and for orders whose
campaign or basket changed after checkout.

Bring the cancellation flow in line with `docs/REFUNDS.md`.

Constraints:

* Production runs PHP 7.4 (see composer.json and Dockerfile). Code must
  run there.
* Do not add dependencies.
* Keep the public API: `App\Kernel` properties, `CheckoutService::place`,
  `CancellationController::cancel`, the domain classes in `App\Domain`,
  `OrderRepository::find` and `Ledger::entries`. The response shape must
  not change.
* Do not change modules other teams own (see docs).
* `php tests/run.php` must pass. Add regression tests to `tests/` that
  run from `tests/run.php` and only use the public API above.
