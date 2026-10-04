# Money splitting rules (finance, v2)

All amounts are integer cents. Finance reconciles every split, so the
rules below are properties that must hold for every input, not just for
typical ones.

## allocate(total, weights)

* `total` can be negative (refunds) and as large as 10^12;
  weights are non-negative integers up to 10^9 (keys are strings, up to 50 keys).
* The shares add up to `total` exactly.
* Every share differs from its exact proportional value
  `total * weight / sum(weights)` by less than one cent, and has the sign of
  `total` (or is zero). A zero weight gets zero.
* The result for a key depends only on the weights, never on the order in
  which the keys are listed: when remainders tie, the lexicographically
  smaller key gets the extra cent.
* Scaling all weights by the same factor does not change the result.
* If all weights are zero, split equally (as if all weights were 1).

## prorate(amount, from, to, periodStart, periodEnd)

* Calendar days, `[from, to)` inside `[periodStart, periodEnd)`; time zones
  play no role (dates only). Amounts are non-negative.
* The whole period costs exactly `amount`; an empty range costs 0.
* Additive: for any `from <= mid <= to`,
  `prorate(from, mid) + prorate(mid, to) == prorate(from, to)`, so a
  subscription changed several times in a period is never over- or
  under-billed by a cent.
* Each result differs from `amount * days / periodDays` by less than one
  cent.

## installments(total, count)

* `total >= 0`, `count >= 1`. Instalments add up to `total`, differ from
  each other by at most one cent, and never increase (larger ones first).
