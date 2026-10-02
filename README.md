# ORC PHP Bench — Trial 001

This is a framework-free PHP codebase. Do not introduce a framework or a
new runtime dependency.

## Task given to the coding agent

`OrderService::checkout()` currently calculates percentage coupons from the
wrong value when a basket has a return-credit adjustment. A coupon must apply
only to positive merchandise lines, never to return credits, and must never
reduce payable merchandise below zero.

Clamp coupon percentages to 0..100. Round half up once per order in integer
cents. Return credits affect the total but do not cap merchandise discounts.
Preserve existing shipping policies, integer response fields and repeatability.
Splitting a merchandise line must not change the resulting discount.

Keep the public response shape unchanged. Make the smallest compatible change
and add or update tests where appropriate.

## Acceptance

Run the public suite with:

```powershell
php tests/run.php
```

The evaluator separately runs hidden regression tests, checks compatibility
with the deployed runtime, and records filesystem/tool telemetry supplied by
the agent runner. Infer compatibility constraints from the existing project.
