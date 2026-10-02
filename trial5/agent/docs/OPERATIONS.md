# Operations

## Runtime

* PHP 7.4 on PHP-FPM, 16 workers per node, 4 nodes behind a load balancer.
  Every HTTP request is served by `public/index.php`, which builds a fresh
  `App\Kernel`. Nothing kept in PHP memory survives the request; workers on
  different nodes share state only through the `FileStore` directory (NFS).
* Clients time out after 2 seconds and retry with the same
  `Idempotency-Key`, often while the first attempt is still running. Users
  also double-click. Concurrent requests for the same order are normal.
* The platform team's `FileStore::transaction()` is the only supported way
  to serialise read-modify-write sequences across workers.

## Customer e-mails

`CancellationService` publishes `refund.issued` events to the CRM outbox
(`App\Notifications\Outbox`) with payload
`{order_id, request_id, refund_cents}`. The e-mail worker sends one e-mail
per event and has no deduplication, so finance requires exactly one event
per ledger entry, written after the ledger entry succeeds. A request that
refunds nothing must not e-mail the customer.

## Ownership

| Path | Owner |
| --- | --- |
| `app/Storage` | platform |
| `app/Ledger` | finance |
| `app/Notifications`, `app/Mail` | CRM |
| `app/Account` | identity |
| everything else in `app/` | payments (you) |
