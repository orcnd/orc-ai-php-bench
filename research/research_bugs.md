# Real-world PHP business-logic bugs to port into a PHP 7.4 billing/e-commerce codebase

I opened every URL below during research. **Confidence** says how sure the root cause is:
- **confirmed**: the code or diff is quoted in the issue or PR.
- **stated**: a maintainer or the reporter describes the mechanism, but no diff is quoted.
- **inferred**: the symptom is documented, but the code pattern is my reconstruction. That is fine for porting, but the pattern is not the upstream code.

Snippets are simplified for porting. Amounts are in currency units unless noted.

---

## A. Money rounding

### 1. Rounding the hours before multiplying by the rate (Kimai)
- URLs: https://github.com/kimai/kimai/issues/5700 (regression), https://github.com/kimai/kimai/issues/5730 (same cause, extreme case)
- **Wrong:** The cost of a time entry is `rate * round(seconds/3600, 2)`. Rounding the hours to 2 decimals before the multiplication makes short entries wrong. At 60/h, 5 min costs 4.80 instead of 5.00 and 10 min costs 10.20 instead of 10.00. At 129,360/h, 1 min costs 2,587.20 instead of 2,156.00.
- **Correct:** Multiply first, then round the money: `round(rate * seconds / 3600, 2)`.
- Confidence: confirmed (the 2.41 diff is quoted in #5700, and the current `src/Timesheet/Util.php` still contains it):
```php
// wrong
$rate = $hourlyRate * round($seconds / 3600, 2, PHP_ROUND_HALF_UP);
return round($rate, 2, PHP_ROUND_HALF_UP);
// right
return round($hourlyRate * $seconds / 3600, 2);
```
- Test: `calculateRate(60, 300)` expects 5.00, actual 4.80. `calculateRate(60, 600)` expects 10.00, actual 10.20. `calculateRate(129360, 60)` expects 2156.00, actual 2587.20.

### 2. Summing rounded per-row decimal hours (Kimai)
- URL: https://github.com/kimai/kimai/issues/5554 (closed as not planned, but the bug is real)
- **Wrong:** The decimal-duration export rounds each row to 2 decimals and then sums the rows. Three 20-minute entries show 0.33 each, so the total is 0.99 h, and a full day shows 7.99 h instead of 8.00 h.
- **Correct:** Sum the seconds first and convert/round once, or round with a carried remainder.
- Confidence: stated.
```php
$total = 0;
foreach ($rows as $r) { $total += round($r['seconds'] / 3600, 2); } // wrong
$total = round(array_sum(array_column($rows, 'seconds')) / 3600, 2);  // right
```
- Test: seconds `[1200, 1200, 1200]` gives a total of 1.00 h expected, 0.99 actual.

### 3. Tax extracted from tax-inclusive prices, rounded per line instead of per document (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/issues/45815 (labelled bug; related report https://github.com/invoiceninja/invoiceninja/issues/4370)
- **Wrong:** The order has a product at 328.79 incl. 21% VAT plus shipping at 3.99 incl. VAT. Tax is extracted per line and rounded per line: 57.06 + 0.69 = 57.75.
- **Correct:** With subtotal-level rounding the exact total tax is 57.7552, which rounds to 57.76. The bug shows when a "round at subtotal" setting exists but one code path still rounds per line.
- Confidence: stated (numbers from the issue; I recomputed them).
```php
function extractTax(float $gross, float $rate): float { return $gross - $gross / (1 + $rate); }
$tax = 0; foreach ($lines as $l) { $tax += round(extractTax($l, 0.21), 2); }  // per-line: 57.75
$tax = round(array_sum(array_map(fn($l) => extractTax($l, 0.21), $lines)), 2); // per-doc: 57.76
```
- Test: `[328.79, 3.99]` at 21% with `roundAtSubtotal=true` expects 57.76, actual 57.75.

### 4. Percentage discount truncated by an `(int)` cast instead of rounded (Sylius)
- URLs: https://github.com/Sylius/Sylius/issues/14522, https://github.com/Sylius/Sylius/pull/14535 (diff), https://github.com/Sylius/Sylius/pull/14669 (merged fix)
- **Wrong:** Prices are stored as integer cents. The discounted price is computed in float and cast with `(int)`, which truncates. 994 cents at 15% off gives 844.9, which becomes 844 ($8.44).
- **Correct:** `(int) round(...)` gives 845 ($8.45).
- Confidence: confirmed.
```php
$price = (int) ($price - $price * $pct);          // wrong
$price = (int) round($price - $price * $pct);     // right
```
- Test: `applyPct(994, 0.15)` expects 845, actual 844.

### 5. Line total uses the unrounded unit price in "round each item" mode (PrestaShop)
- URL: https://github.com/PrestaShop/PrestaShop/issues/33962 (verified; test fixed in PR #34076)
- **Wrong:** In "round each item" mode, a pack shows 4 × 22.94 + 15.48 as 107.26. The line was computed from the unrounded unit price: 22.9449 × 4 = 91.7796, which rounds to 91.78.
- **Correct:** In per-item mode, round the unit price first, then multiply: 4 × 22.94 = 91.76, total 107.24.
- Confidence: inferred (the numbers come from the issue; the unit price 22.9449 is my reconstruction that reproduces them).
```php
$line = round($unit * $qty, 2);          // wrong in ROUND_ITEM mode
$line = round($unit, 2) * $qty;          // right in ROUND_ITEM mode
```
- Test: unit 22.9449, qty 4, plus a 15.48 line, mode ROUND_ITEM: expects 107.24, actual 107.26.

### 6. Unrounded subtotal minus a rounded discount gives a negative total (PrestaShop)
- URL: https://github.com/PrestaShop/PrestaShop/issues/37924 ("PR available")
- **Wrong:** The calculator computes the total without discounts unrounded (e.g. 21.525 tax-excl) and subtracts the rounded discount (21.53). A 100% "free order" ends at -0.005, which rounds to -0.01, and order validation throws.
- **Correct:** Round both operands consistently, and clamp the result at 0.
- Confidence: stated (the issue describes this mechanism and gives the 21.525 example).
```php
$total = $productsTotalUnrounded - round($discount, 2);          // wrong
$total = max(0, round($productsTotalUnrounded, 2) - round($discount, 2)); // right
```
- Test: product 21.525, discount 100% of the rounded value (21.53): total expects 0.00, actual -0.01 or negative.

### 7. Inclusive taxes compounded instead of parallel (Invoice Ninja)
- URL: https://github.com/invoiceninja/invoiceninja/issues/12072 (closed, labelled bug)
- **Wrong:** An expense of 1000 gross with two inclusive 10% taxes gets net = 1000 / 1.10 / 1.10 = 826.45, with tax A 174.00 and tax B 157.76.
- **Correct:** Parallel, non-compound rates: net = 1000 / (1 + 0.10 + 0.10) = 833.33, with each tax 83.33.
- Confidence: stated (the reporter describes the sequential division).
```php
$net = $gross; foreach ($rates as $r) { $net /= (1 + $r); }   // wrong (compound)
$net = $gross / (1 + array_sum($rates));                       // right (parallel)
```
- Test: gross 1000, rates `[0.10, 0.10]`: net expects 833.33, actual 826.45.

### 8. Discount applied before tax in one code path and not in another (Akaunting)
- URL: https://github.com/akaunting/akaunting/issues/650 (closed, milestone 1.3.4)
- **Wrong:** The invoice is created correctly: 200, minus 10% discount = 180, plus 18% tax = 32.40, total 212.40. On the show/pay screen the tax is recomputed against the wrong base (the reporter saw 20 × 18% = 3.60), so the total changes after creation.
- **Correct:** Use one function for the tax base, `(subtotal - discount) * rate`, in every path.
- Confidence: stated (symptom). The port is two code paths that compute the tax base differently.
```php
// create path
$tax = ($subtotal - $discount) * $rate;
// show/pay path (bug)
$tax = $discount * $rate;   // or $subtotal * $rate: any base other than (subtotal - discount)
```
- Test: subtotal 200, discount 10%, tax 18%: `Invoice::total()` should be 212.40 on every path.

### 9. Quantity edit drifts by a cent with tax-inclusive prices (WooCommerce)
- URLs: https://github.com/woocommerce/woocommerce/issues/51507, fix https://github.com/woocommerce/woocommerce/pull/53054
- **Wrong:** Prices include 10% tax and the product costs 6.00. Changing qty 1 to 2 and back to 1 gives 6.01. Changing qty 1 to 3, then 1, then 3 gives 18.01 instead of 18.00. The edit path rounded the ex-tax unit to price decimals (2 dp); the add path kept internal precision (6 dp). The next edit then derives a new unit price from the rounded line.
- **Correct:** Keep the full-precision ex-tax unit (or the gross unit) and round only the final line amounts.
- Confidence: stated (the PR explains the precision mismatch).
```php
$unitNet = round($lineNet / $oldQty, 2);       // wrong: precision loss compounds over edits
$unitNet = round($lineNet / $oldQty, 6);       // right: internal precision
$lineGross = round($unitNet * $newQty * 1.10, 2);
```
- Test: gross 6.00 at 10% incl., qty edits 1 to 3 to 1 to 3: final gross expects 18.00, actual 18.01.

---

## B. Refunds and credit notes

### 10. Float sum compared with `>` rejects a valid full refund (Invoice Ninja)
- URL: https://github.com/invoiceninja/invoiceninja/issues/12231 (closed)
- **Wrong:** A 30.20 payment is refunded across two invoices, 11.85 + 18.35. The float sum is 30.200000000000003, so `$sum > $refundable` is true and the refund gets 422 "max refundable exceeded". Another failing case is 11.26 + 17.76 against 29.02. The reporter estimates about 1 in 9 two-invoice full refunds fail.
- **Correct:** Compare in integer cents or bcmath, or round both sides to 2 dp before comparing.
- Confidence: confirmed (`var_dump(11.85 + 18.35 > 30.20)` is true; I verified locally).
```php
if (array_sum($amounts) > $payment->amount - $payment->refunded) fail();                 // wrong
if (bccomp(bcsum($amounts), bcsub($payment->amount, $payment->refunded, 2), 2) === 1) fail(); // right
```
- Test: payment 30.20, refunded 0, refund `[11.85, 18.35]`: expects OK, actual rejected.

### 11. Full refund recomputes the total from separately rounded net and tax sums (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/pull/34641 (merged; closes #30263)
- **Wrong:** With per-line tax rounding, 24% tax, and two items at 2.10 incl. tax, refunding everything gives 4.21, which exceeds the 4.20 that was charged. The net and tax totals were rounded separately across lines: net 3.387 rounds to 3.39, and the per-line taxes are 0.41 + 0.41 = 0.82.
- **Correct:** When per-line rounding is on, round each refund line (net and tax) and then sum, so a full refund equals the amount charged.
- Confidence: stated (numbers from the PR; I re-derived the arithmetic).
```php
$net = round(array_sum($lineNets), 2); $tax = array_sum(array_map(fn($t) => round($t, 2), $lineTaxes)); // wrong mix
foreach ($lines as $l) { $total += round($l->net, 2) + round($l->tax, 2); }                          // right
```
- Test: two lines of 2.10 gross at 24%, full refund: expects 4.20, actual 4.21.

### 12. Taxes extracted from an inclusive refund without rounding, for multiple rates (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/pull/63046 (merged)
- **Wrong:** The refund API takes a tax-inclusive amount and three non-compound rates (1%, 3.25%, 6.25%) on a 50.00 item. Refunding 55.26 produced a line refund of -50.01 instead of -50.00. The extracted tax amounts kept full precision, so `base = gross - sum(taxes)` drifted.
- **Correct:** Round each extracted tax to price decimals first, then compute base = gross - sum(rounded taxes).
- Confidence: stated.
```php
$taxes = array_map(fn($r) => $gross * $r / (1 + array_sum($rates)), $rates);
$base  = $gross - array_sum($taxes);                                   // wrong
$taxes = array_map(fn($t) => round($t, 2), $taxes);
$base  = round($gross - array_sum($taxes), 2);                         // right
```
- Test: gross 55.26, rates `[0.01, 0.0325, 0.0625]`: base expects 50.00, actual 50.01.

### 13. Deleting the only refund leaves the order "refunded" (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/issues/68658 (closed)
- **Wrong:** Creating a full refund sets the order to `refunded`. Deleting that refund only clears caches, so `totalRefunded()` is 0 but the status stays `refunded`.
- **Correct:** When a refund is deleted, re-evaluate the order: no refunds means restore `completed` (or the previous status); a partial refund means not fully refunded.
- Confidence: confirmed (the issue quotes both code paths).
```php
function deleteRefund(Order $o, int $refundId) { $o->removeRefund($refundId); /* status untouched */ }
```
- Test: order 100, refund 100 gives `refunded`. Delete the refund: status expects `completed`, actual `refunded`.

### 14. Invoice paid in a foreign currency never closes (Dolibarr)
- URL: https://github.com/Dolibarr/dolibarr/issues/25898
- **Wrong:** The paid check compares amounts converted to company currency. The invoice is 92.59 SEK at rate 11.681 (7.92 EUR) and is fully paid in SEK. The remaining amount in SEK is 0.00, but the converted payments differ from 7.92 EUR by 0.01, so the invoice stays open. A second example is 222.57 EUR / 190.75 GBP at rate 1.16686.
- **Correct:** Decide "fully paid" in the document currency. Book any company-currency difference as an FX gain or loss.
- Confidence: stated.
```php
$paid = array_sum(array_map(fn($p) => round($p->amount / $rate, 2), $payments)); // in EUR
if ($paid >= $invoice->totalEur) close();                     // wrong
if (round($invoice->totalDoc - array_sum(array_column($payments,'amount')), 2) <= 0) close(); // right
```
- Test: invoice 92.59 in the document currency, rate 11.681, payments `[50.00, 42.59]` in the document currency: expects closed, actual open. Pick payment splits so the per-payment rounded conversions sum below `round(92.59/11.681, 2)`.

---

## C. Coupons, discounts, promotions

### 15. Discount amount cast with `(float)` before validation, comma decimals (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/pull/68251 (merged; same fix in #67704)
- **Wrong:** The coupon min/max spend setters do `(float)$amount` before comparing, but store `wc_format_decimal($amount)`. With a comma decimal separator, `(float)'100,50'` is 100.0. So min '100,50' with max '100,00' is accepted (invalid), and min '100.50' with max '100,75' is rejected (valid).
- **Correct:** Normalize once, then validate and store the same value.
- Confidence: confirmed.
```php
$amount = (float) $amount;                 // wrong: '100,50' -> 100.0
$amount = (float) str_replace(',', '.', $amount); // right (normalize first)
if ($amount < $this->minimum) throw new InvalidArgumentException();
```
- Test: `setMinimum('100,50'); setMaximum('100,00')` expects an exception, actual accepted. `setMinimum('100.50'); setMaximum('100,75')` expects OK, actual exception.

### 16. Pair validation on load drops BOTH bounds (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/pull/69237 (merged, backported #69242)
- **Wrong:** Legacy data has min spend 150 and max spend 100. Loading through `setProps()` runs a min ≤ max pair check, which rejects both values. The coupon loads with min 0 and max 0, so it applies to any cart, including carts under 150.
- **Correct:** On load, run the individual setters: keep the minimum, drop the invalid lower maximum. Run the strict pair check only for user edits.
- Confidence: stated.
```php
function setProps(array $p) {
  if (isset($p['min'], $p['max']) && $p['min'] > $p['max']) return; // wrong: drops both
  ...
}
```
- Test: load `['min' => 150, 'max' => 100]`, then `isValidFor(cartTotal: 120)` expects false (below min), actual true.

### 17. Percentage and whole-cart fixed promotions don't stack (Bagisto)
- URL: https://github.com/bagisto/bagisto/issues/3638 (closed, "Bug Fixed")
- **Wrong:** Two rules apply: 10% off per product and 10 off the whole cart. The expected discount is -20.00; Bagisto showed -18.00.
- **Correct:** Each rule's discount should match its definition. The second rule must not be scaled by, or computed on top of, the first rule's result unless the rule says so.
- Confidence: inferred (the issue gives only the totals, and the cart contents are in screenshots). A port that reproduces "18 instead of 20" on a 100 subtotal: the fixed whole-cart amount is distributed across items in proportion to item prices *after* the percentage discount, and then scaled by the discounted/original ratio, i.e. applied as a percentage of the remaining cart. Alternatively, ship it as the generic "each rule computed on the already-discounted base" bug.
- Test: items `[100]`, rules `[pct 10% per item, fixed 10 whole cart]`: discount expects 20.00.

### 18. Uneven per-unit discount distribution: line total uses the first unit × quantity (Sylius)
- URLs: https://github.com/Sylius/Sylius/issues/11112, fix PR #11114
- **Wrong:** An order-level promotion is split across units and does not divide evenly (e.g. 10.00 over 3 units is 3.34 / 3.33 / 3.33). The item subtotal is computed as `qty * (unitPrice + firstUnit.adjustments)`, so it gives 3 × -3.34 = -10.02.
- **Correct:** Sum the adjustments over all units.
- Confidence: confirmed (the template code is quoted).
```php
$subtotal = $item->qty * ($item->unitPrice + $item->units[0]->adjustmentTotal());  // wrong
$subtotal = $item->unitPrice * $item->qty + array_sum(array_map(fn($u) => $u->adjustmentTotal(), $item->units)); // right
```
- Test: unit price 20.00, qty 3, discount 10.00 split `[-3.34, -3.33, -3.33]`: subtotal expects 50.00, actual 49.98.

---

## D. Inventory

### 19. Packaging-quantity rounding loses the sign and runs after the amount calculation (Dolibarr)
- URL: https://github.com/Dolibarr/dolibarr/pull/41111 (merged)
- **Wrong:** With packaging of 6 and unit price 10, updating a line to qty 7 stores qty 12 but total 70, because the amount was computed before the qty was rounded up. Adding qty -4 (a return) stores qty 6 and total 60, because of `abs()`.
- **Correct:** Round the absolute value up to the next pack, keep the sign, and do it *before* computing amounts: qty 12 gives total 120; qty -4 gives -6 and total -60.
- Confidence: confirmed (described in the PR, with the before/after pattern).
```php
$total = $qty * $price;                       // computed first (wrong order)
$qty = ceil(abs($qty) / $pack) * $pack;       // abs() loses the sign (wrong)
// right:
$qty = ($qty < 0 ? -1 : 1) * ceil(abs($qty) / $pack) * $pack;
$total = $qty * $price;
```
- Test: `updateLine(qty 7, pack 6, price 10)` expects (12, 120), actual (12, 70). `addLine(qty -4)` expects (-6, -60), actual (6, 60).

(Also seen: https://github.com/woocommerce/woocommerce/issues/12467, where concurrent checkouts both pass a read-then-write stock check and stock goes negative. This is a race condition, so it is hard to unit-test; skip it unless you model reservations.)

---

## E. Dates, time zones, recurring billing

### 20. DST: the future send time uses today's UTC offset (Invoice Ninja)
- URL: https://github.com/invoiceninja/invoiceninja/issues/12158 (closed)
- **Wrong:** A quarterly recurring invoice sends at 06:00 America/New_York on 2026-08-05. The next send is computed as `nextDateUtc - currentOffset` using August's offset (EDT, -4), so it lands at 05:00 local on 2026-11-05 (EST, -5).
- **Correct:** Do the calendar arithmetic in the local time zone (DateTime with a TZ), then convert to UTC. Never reuse an offset taken at a different date.
- Confidence: stated (the issue identifies `timezone_offset()` returning the current offset).
```php
$offset = (new DateTime('now', $tz))->getOffset();                  // wrong: offset of today
$nextUtc = (new DateTime('2026-11-05 06:00', new DateTimeZone('UTC')))->modify((-$offset).' seconds');
$next = (new DateTime('2026-11-05 06:00', $tz))->setTimezone(new DateTimeZone('UTC')); // right
```
- Test: with 'now' = 2026-08-05 in NY, local 06:00 on 2026-11-05 expects UTC 11:00, actual 10:00.

### 21. Month-end boundary: the date-range end treated as midnight (Firefly III)
- URLs: https://github.com/firefly-iii/firefly-iii/issues/8937 (reconciling May 1–31 omits May 31 transactions; selecting May 1–Jun 1 includes them), https://github.com/firefly-iii/firefly-iii/issues/9416 (a bill dated the 31st is not marked paid when the transaction time is after 00:00; fixed in v6.1.22), https://github.com/firefly-iii/firefly-iii/issues/9867 (a 2025-01-31 transaction counted in February after a certain time of day; fixed in v6.2.12)
- **Wrong:** The range is `[start, end]` with `end = 2024-05-31 00:00:00`, so anything later on the 31st is excluded (or slips into the next period after a TZ shift).
- **Correct:** Use `end->endOfDay()` (23:59:59) or a half-open interval `< end + 1 day`, with both sides in the same TZ.
- Confidence: inferred (the symptoms are documented and the fixes confirmed, but no diff is quoted).
```php
$q->where('date', '>=', $start)->where('date', '<=', $end);             // $end = '2024-05-31 00:00:00' (wrong)
$q->where('date', '>=', $start)->where('date', '<', (clone $end)->modify('+1 day')); // right
```
- Test: a transaction at 2024-05-31 14:00 in report(2024-05-01, 2024-05-31) expects included, actual excluded.

### 22. Weekly subscription marked paid for the whole month after the first payment (Firefly III)
- URL: https://github.com/firefly-iii/firefly-iii/issues/12468 (fixed in v6.7.0)
- **Wrong:** A weekly bill that occurs several times in a month disappears from "to pay" once any payment exists in that month. The paid check is "any linked transaction in [monthStart, monthEnd]".
- **Correct:** Check each expected occurrence date (or its own pay window) for a matching payment, and count only the unpaid occurrences.
- Confidence: inferred (the symptom is confirmed; the code pattern is reconstructed).
```php
$paid = count(paymentsBetween($monthStart, $monthEnd)) > 0;   // wrong: one payment covers all
foreach (occurrences($bill, $monthStart, $monthEnd) as $d) { if (!paymentFor($d)) $due += $bill->amount; } // right
```
- Test: a weekly bill of 10 with occurrences Jul 1/8/15/22/29 and one payment on Jul 1: outstanding expects 40, actual 0.

### 23. Quarter offset without wrap-around (Invoice Ninja)
- URL: https://github.com/invoiceninja/invoiceninja/issues/11479 (labelled bug and fixed)
- **Wrong:** The `:QUARTER+1` template variable on an invoice dated 2025-12-09 (Q4) renders "5".
- **Correct:** Wrap modulo 4 (and carry the year): Q4+1 is Q1 (of 2026). Note that the reporter wrote "expected 4". The issue confirms only that 5 is wrong; wrap-around is the sensible spec.
- Confidence: stated (symptom).
```php
$q = (int) ceil($date->format('n') / 3) + $offset;          // wrong: 5
$q = (((int) ceil($date->format('n') / 3) - 1 + $offset) % 4 + 4) % 4 + 1; // right, also handles negative offsets
```
- Test: 2025-12-09 with +1 expects 1 (year 2026), actual 5. 2025-01-15 with -1 expects 4 (year 2024).

(Related, same repo: https://github.com/invoiceninja/invoiceninja/issues/11424 resolves `:MONTHYEAR`-style keywords against "now" instead of the invoice date, so a November invoice re-rendered in December shows December.)

---

## F. Reports, pagination, ordering

### 24. Offset pagination without a deterministic ORDER BY tie-breaker (Invoice Ninja)
- URLs: https://github.com/invoiceninja/invoiceninja/issues/10933 (duplicate clients across pages), fix https://github.com/invoiceninja/invoiceninja/pull/11936
- **Wrong:** Listing is paginated with LIMIT/OFFSET and ordered by nothing, or by a non-unique column such as `created_at`. Tied rows can come back in a different order on each page request, so records repeat or go missing across pages.
- **Correct:** Always append the primary key as the final sort key.
- Confidence: confirmed (the diff is quoted in the PR).
```php
if ($sort) $qb->orderBy($col, $dir);
return $qb;                                   // wrong
if ($sort) $qb->orderBy($col, $dir);
$qb->orderBy('id', 'asc'); return $qb;        // right
```
- Port as an in-memory paginator using unstable ordering: e.g. `usort` by `created_at` over rows with equal timestamps, re-shuffled per call to simulate the DB. Test: union of all pages expects every id exactly once.

### 25. Running balance takes the FIRST transaction of the previous day instead of the LAST (Firefly III)
- URL: https://github.com/firefly-iii/firefly-iii/issues/12862 (fixed)
- **Wrong:** For a new transaction, the opening balance comes from the "previous" transaction, found by date only (or by date ASC, picking the first). With several transactions on 2026-09-03, the new 09-04 transaction got balance_after 1433.56 instead of 452.97.
- **Correct:** Take the previous row ordered by `date DESC, order DESC, id DESC`, i.e. the last one before.
- Confidence: stated (the issue gives the expected ordering).
```php
$prev = first(array_filter($tx, fn($t) => $t->date < $new->date)); // wrong (first of the earlier rows, ASC order)
usort($earlier, fn($a,$b) => [$b->date,$b->order,$b->id] <=> [$a->date,$a->order,$a->id]); $prev = $earlier[0]; // right
```
- Test: day 1 has +100 (id 1) and -50 (id 2); day 2 adds -10. Balance_after expects 40, actual 90.

### 26. "Budget remaining" subtracts from the wrong budget field (Kimai)
- URLs: https://github.com/kimai/kimai/issues/2820, fix https://github.com/kimai/kimai/pull/2821
- **Wrong:** With a money budget of 30,000 and 156.25 billed, the page showed 863,843.75 still open: `timeBudget (seconds) - rateBillable`.
- **Correct:** `budget - rateBillable` gives 29,843.75.
- Confidence: confirmed (the one-line diff is quoted). This works as a "plausible field mix-up" bug, e.g. a `budget` vs `timeBudget` property on the same entity.
- Test: budget 30000, timeBudget 864000, billed 156.25: remaining expects 29843.75.

---

## G. PHP loose comparison

### 27. `0 == ''` is true in PHP 7, so a zero tax is displayed as "–" (WooCommerce)
- URL: https://github.com/woocommerce/woocommerce/issues/13565 (closed, milestone 3.0.0)
- **Wrong:** The order-item views check `if ($tax_amount == '')` to decide whether data exists. An integer 0 tax compares equal to '' in PHP 7.x, so the view prints "–" (no data) instead of 0.00. A string "0" behaves differently ("0" == '' is false), so the output depends on storage type. **This bug only reproduces on PHP < 8**, which suits your 7.4 target.
- **Correct:** `if ($tax_amount === '' || $tax_amount === null)`, or `isset` with `is_numeric`.
- Confidence: confirmed (the issue quotes the mechanism).
```php
echo ($tax == '') ? '&ndash;' : format_money($tax);           // wrong (PHP 7)
echo ($tax === '' || $tax === null) ? '&ndash;' : format_money($tax); // right
```
- Test (PHP 7.4): `renderTax(0)` expects "0.00", actual "–". `renderTax('0')` expects "0.00" (already correct). `renderTax('')` expects "–".

---

## Notes and other candidates I opened but did not write up
- Firefly III https://github.com/firefly-iii/firefly-iii/issues/12545: API validation capped the monthly "day of month" `moment` at 10 instead of 31 (copy-paste validation bound; fixed in v6.7.0). This would make a nice small bounds bug.
- Firefly III https://github.com/firefly-iii/firefly-iii/issues/12401: a subscription allowed min amount > max amount (missing cross-field validation).
- Kimai https://github.com/kimai/kimai/pull/5743: the `modified_after` API filter was converted from the user's TZ, but the column is stored in UTC.
- Invoice Ninja https://github.com/invoiceninja/invoiceninja/issues/11854: reminders stop forever when the computed next reminder date is already in the past (no catch-up/skip logic).
- Not verified as code-level bugs, so not included: the WooCommerce coupon per-item rounding report #47719 (closed not planned), Laravel Cashier #1572 (needs more info), Firefly #12614 and #5841 (31st-of-month recurrences; no root cause given), PrestaShop #32762 (partial-refund order slip with a 0.02 drift, caused by the `partial` flag not being set).
- The unauthenticated GitHub search API was rate-limited early (the IP is shared), so most discovery used repository issue-search pages. Everything listed above was opened individually.
