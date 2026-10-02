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

## Failures

* Workers die at arbitrary points: OOM kills, the 30 s
  `max_execution_time`, and every deploy terminates running workers. The
  platform team runs the `STORE_CRASH_AT` chaos hook (see `FileStore`)
  against staging nightly. After a crash the client retries with the same
  `Idempotency-Key`, but other requests for the order (other keys) may
  arrive first.
* There is no transaction spanning the ledger, the outbox and your own
  documents. The ledger is the system of record for money: finance can
  read every booking with `Ledger::entries()` (entries carry the
  request id).
* Requirements after any crash, once the request has been retried:
  at most one ledger entry per order and idempotency key, refunds never
  exceed the customer's entitlement whichever keys arrive, the retry
  returns the amount that was booked, and the customer gets exactly one
  `refund.issued` e-mail per ledger entry.
* The CRM outbox has maintenance windows: `Outbox::publish()` then throws
  `OutboxUnavailableException`. Money must not wait for e-mail: the
  cancellation still succeeds with its normal response. The missing event
  must be published no later than the next cancellation request for that
  order (any key, including a retry) after the outbox is back.

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
