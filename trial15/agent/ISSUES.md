# Issues for this PR

### #201 Local pickup orders are taxed with the customer's address
"Customers who choose local pickup (`shippingMethod = local_pickup`) are
charged VAT of their shipping country. For pickup the place of supply is our
store, so the store base address must be used." (EU VAT, B2C)

### #202 Wrong shipping cost after changing the cart
"Add a mug, go to checkout (4.90 shipping), go back, add a 20 kg parcel:
shipping still says 4.90." Note from ops: the carrier API is billed per
call; we cannot call it more than once per distinct package.

### #203 Decline reason missing for card, PayPal and Klarna payments
"When Stripe, PayPal or Klarna decline, the customer just sees 'Payment
failed' instead of the reason (insufficient funds, expired card, ...).
`PaymentFailed::declineCode()` always returns 'unknown'."

### #204 Coupons expire at the wrong time
"Our US store (America/New_York) rejected a coupon valid until 2026-10-31
at 21:00 on 31 October. Store timezone is configured, but it seems to be
ignored."

### #205 Customer names with quotes break the CSV export
'A customer called `Joe "JJ" Smith` breaks the accounting export: Excel
shows the rest of the line in one cell.'
