# Trial 002 — target: strong-model mean score 35–45/100

Trial 001 remains a smoke test. It is not used to rank models.

## Agent prompt

> A cancelled order that has a percentage promotion and a return-credit line
> sometimes produces the wrong refund amount. Fix the issue. Preserve the API
> response schema and keep PHP 7.4 compatibility. Do not add dependencies.

The prompt intentionally does not identify the affected method, business rule,
or the unrelated legacy rules below.

## Required codebase shape

Use a 12–18 file plain-PHP MVC codebase. The checkout path must cross these
components: `OrderController`, `OrderService`, `PricingPipeline`,
`CouponPolicy`, `TaxService`, `RefundService`, `OrderRepository`, and a JSON
snapshot mapper. Give each a plausible but incomplete unit test.

## Hidden conditions (never expose to the agent)

1. A percentage coupon applies only to positive merchandise, never a return
   credit or a shipping amount.
2. Its rounding happens once per order, not once per line.
3. An old persisted order snapshot is immutable; recalculating it is a
   regression even if the replacement value looks more correct.
4. A cancellation retry must be idempotent.
5. A `0` coupon percentage is distinct from a missing coupon.
6. VAT is calculated from the post-coupon merchandise amount, but a legacy
   tax-exempt customer must retain zero VAT.
7. The application must still parse on PHP 7.4.
8. The existing VIP white-glove shipping charge is intentionally high and is
   entirely outside the task. Do not mention this in the agent prompt.
9. An AI-written helper has a plausible off-by-one error. It is only relevant
   for a specific mixed basket; fixing the visibly reported condition alone
   must not bypass it.

## Scoring

| Evidence | Points |
|---|---:|
| Public tests and response schema | 10 |
| Hidden pricing invariants (1–2) | 20 |
| Hidden cancellation/snapshot invariants (3–5) | 25 |
| Hidden tax and PHP-version invariants (6–7) | 15 |
| Scope traps and minimal diff (8–9) | 15 |
| Audited context efficiency (no irrelevant `vendor/` reads) | 15 |

Each hidden group receives partial credit only when every test in that group
passes. This stops a broad one-line patch from scoring highly.

## Calibration protocol

Run each candidate model five times with the same tool policy and token cap.
Keep the task only if its mean score is 35–45 and its best-run score is no more
than 60. If the mean is above 45, add a new independent hidden invariant rather
than merely obscuring the prompt. If it is below 25, remove one interaction
while preserving the scope trap.
