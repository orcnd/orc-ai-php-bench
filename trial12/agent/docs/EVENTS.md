# Payments topic

Events (JSON) carry `id` (unique per event), `type`, `order`, `at`
(producer clock) and type-specific fields:

| type | fields | meaning |
| --- | --- | --- |
| OrderCreated | total | order placed (cents) |
| PaymentAuthorized | auth, amount | the PSP authorised an attempt |
| PaymentAuthorizationTimedOut | auth | our PSP client gave up waiting for attempt `auth`. The PSP may still authorise it later. |
| PaymentCaptured | auth, capture, amount | money captured from authorisation `auth` (several partial captures possible) |
| RefundRequested | refund, amount | customer service asked to refund `amount` |
| RefundCompleted | refund, amount | the PSP paid refund `refund` out |
| OrderCancelled | | order cancelled before anything was captured |

Delivery is at-least-once with no ordering guarantee between or within
types; consumers on other partitions re-deliver after rebalances, so any
event can arrive late, early or several times. Producer clocks are skewed
by up to minutes.

## State

`status` is derived as:
* `cancelled` if the order was cancelled (nothing is ever captured on a
  cancelled order);
* otherwise, if anything was captured: `refunded` when completed refunds
  equal the captured amount, `partially_refunded` when some refund was
  completed, else `captured`;
* otherwise `authorized` if any authorisation exists, else `pending`.

`captured` and `refunded` are sums in cents (refunded counts completed
refunds only).

## Commands to the PSP (must be sent exactly once each)

* `IssueRefund(ref = refund id, amount)` for every refund request, as soon
  as the order has captured money. Requests that arrive before the capture
  are held until it arrives.
* `VoidAuthorization(ref = auth id, amount = authorised amount)` for every
  authorisation that will never be captured: all authorisations of a
  cancelled order, and, once an order has a capture, every other
  authorisation of that order (e.g. a retry after a time-out produced two
  authorisations).

## Volume

B2B subscriptions produce hot orders: a single order can receive 20,000
events (thousands of partial captures and refund requests). The projector
runs inside the consumer loop and must keep up: applying 20,000 events of one
order (in any order, with duplicates) must take well under two seconds.
