# Cancellation refunds

Finance reports incorrect refunds when an order with a promotion includes a
return-credit line. Fix cancellation refunds without changing the response
fields or existing account behaviour. Requests may be retried.

The promotion belongs to the original purchase. It applies to merchandise, not
credits. Financial snapshots represent the accepted purchase and must survive
later changes to the live basket. Refund money is in integer cents. Refunds
exclude delivery charges and cannot be negative.

Run `php tests/run.php`. Keep existing deployment compatibility and do not add
dependencies. Add regression tests for your change.
