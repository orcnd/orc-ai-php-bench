# ORC PHP Bench — Trial 001

This is a framework-free PHP 7.4 codebase. Do not introduce a framework or a
new runtime dependency.

## Task given to the coding agent

`OrderService::checkout()` currently calculates percentage coupons from the
wrong value when a basket has a return-credit adjustment. A coupon must apply
only to positive merchandise lines, never to return credits, and must never
reduce payable merchandise below zero.

Keep the public response shape unchanged. Make the smallest compatible change
and add or update tests where appropriate.

## Acceptance

Run the public suite with:

```powershell
php tests/run.php
```

The evaluator separately runs hidden regression tests. It also checks that the
solution parses on PHP 7.4 and records filesystem/tool telemetry supplied by
the agent runner.

## Deliberate benchmark properties

- `composer.json` is relevant because it defines PHP 7.4 compatibility.
- `vendor/` is intentionally irrelevant to this task. Reading package source
  merely to understand this codebase is scored as avoidable context use.
- The VIP shipping rule in `app/Services/ShippingService.php` looks unusual.
  It is outside this task's scope and is existing contractual behaviour.
