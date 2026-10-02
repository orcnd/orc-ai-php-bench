# FIN-2291: wrong refunds on cancelled orders

Finance escalated several incorrect refunds. The reported case: an order
with a 20% promotion, a 10.00 EUR item, a 5.00 EUR return credit and 5.00
EUR shipping was cancelled six weeks after purchase and refunded 4.00 EUR instead of 3.00 EUR.
Audit also found refunds that differ from what finance computes by hand
for partial cancellations, for retried requests and for orders whose
campaign or basket changed after checkout. Customer service additionally
needs unit-level cancellations (cancel 2 of 5 units), wholesale accounts
place very large orders, migrated v2 orders must keep working, and
cancellations keep arriving during the month-end ledger close.

Bring the cancellation flow in line with our documentation (`docs/`).

Ops note from last week's incident review (INC-4410): "some customers got
two refund e-mails, one got refunded twice, one never got an e-mail;
probably retries, maybe the deploy on Tuesday".

Constraints:

* Production runs PHP 7.4 (see composer.json and Dockerfile). Code must
  run there.
* Do not add dependencies.
* Keep the public API: `App\Kernel` (constructor and properties), `CheckoutService::place`,
  `CancellationController::cancel`, the domain classes in `App\Domain`,
  `OrderRepository::find`/`save`, `Ledger` and `Outbox`. The response shape must
  not change.
* Do not change modules other teams own (see docs).
* `php tests/run.php` must pass. Add regression tests to `tests/` that
  run from `tests/run.php` and only use the public API above.
