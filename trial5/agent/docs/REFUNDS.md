# Refund rules (finance-approved, v4)

This document is the source of truth for cancellations. Where the code
disagrees with it, the code is wrong.

## Money

All amounts are integer cents. A line total is `unitCents * quantity`.
Line types: `merch` (merchandise, >= 0), `credit` (store/return credit
applied at checkout, <= 0) and `shipping` (delivery charge, >= 0).

Limits: wholesale accounts place orders of up to 5,000,000,000 cents
(50 million EUR) per order; quantities are at most 1,000,000 per line.
Every amount and intermediate result must be exact at these limits on a
64-bit PHP build (no float arithmetic, no lost cents).

## Promotions

A promotion applies to the **merchandise subtotal only** (sum of `merch`
line totals). Credits and shipping are never discounted and never reduce
the discount base.

* `percent`: discount = merchandise subtotal * value / 100, rounded half
  up to whole cents (e.g. 2.5 -> 3, 2.49 -> 2).
* `fixed`: discount = min(value, merchandise subtotal).

Promotion terms are fixed when the order is placed. Marketing may later
change a campaign's value or retire it; existing orders keep the terms
they were placed with.

## Discount allocation

The order discount is allocated to merchandise lines in proportion to
their line totals using the largest-remainder method:

1. each line gets floor(discount * lineTotal / subtotal);
2. the cents still unallocated go one each to the lines with the largest
   remainders of that division;
3. equal remainders are resolved in checkout line order (earlier first).

Allocations always sum exactly to the order discount. The net value of a
merchandise line is its total minus its allocation. `OrderSnapshot`
exposes `discount` and `allocations` (keyed by line id) for reporting.

### Migrated orders

Orders migrated from the v2 platform are saved directly with
`OrderRepository::save()`. Their stored `discount` and `allocations` were
computed by older rules (allocations may not even sum to the discount)
and are what the customer was actually charged. Refunds always use the
stored allocations; never recompute pricing for a placed order.

## Snapshots

`CheckoutService::place()` records an `OrderSnapshot`: the accepted
purchase. Customers keep editing their basket after checkout (quantity,
price refresh, removals) and marketing keeps editing campaigns. Neither
may change a placed order's snapshot, and cancellations must never modify
it either.

## Cancellations

`CancellationController::cancel(orderId, lines, idempotencyKey)`
cancels some or all units of an order's lines. Each element of `lines`
is either a line id (list element: cancel all remaining units of that
line) or `lineId => unitCount` (cancel that many more units). Both forms
may be mixed in one request. A unit count below 1 or above the line's
remaining (not yet cancelled) units rejects the whole request with
`InvalidArgumentException`. The response shape is
`{order_id, refund_cents, status}` where status is `refunded` when
refund_cents > 0, otherwise `nothing_to_refund`.

* Shipping is never refunded. Shipping and credit line ids may be listed;
  they contribute nothing by themselves.
* The cancelled value of a merch line with `c` of its `q` units cancelled
  so far is `floor(net * c / q)`. Example: net 1000, q 3: cancelling one
  unit at a time refunds 333, 333, then 334.
* Credits are consumed against the order's cumulative refund. After each
  request, the customer's total entitlement is
  `max(0, sum of cancelled values of all merch lines + sum of all credit line totals)`
  and the request refunds the increase in entitlement. Partial
  cancellations therefore always add up to the full-cancellation refund
  and a refund is never negative.
* A bare line id whose units are all cancelled already is ignored.
* An unknown line id rejects the whole request with
  `InvalidArgumentException` and changes nothing.
* Every request with refund_cents > 0 writes exactly one ledger entry
  `{order_id, request_id, amount}`. Zero refunds write no ledger entry.

## Month-end close

While finance has the ledger closed, `Ledger::record()` throws
`App\Ledger\LedgerClosedException`. A cancellation that needs a ledger
entry must then fail with that exception and have no effect at all: no
units cancelled, no response stored for the idempotency key. The client
retries the same key after the ledger reopens and must get the full
refund. Requests that refund nothing do not touch the ledger and succeed
during the close.

## Retries

Clients retry with the same idempotency key. The key is generated per
order, so two different orders may use the same key value. A retried key
returns the original response unchanged (even if the request body,
basket or catalogue changed since) and has no further effect.

## Downstream consumers

`App\Mail\ReceiptFormatter` output is machine-parsed by the accounting
mailbox importer. The account module (`App\Account`) is owned by the
identity team and contains deliberate behaviour for an ongoing incident
investigation; it is out of scope for the payments team. `App\Ledger` is
owned by finance and must not be changed.
