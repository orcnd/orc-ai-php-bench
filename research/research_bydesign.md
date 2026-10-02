# Chesterton's-fence cases in PHP business code (research notes)

Every source below was opened during this research (GitHub issue/PR via REST API or page fetch, bugs.php.net, wiki.php.net, project docs, or raw source on GitHub). Quotes are short and paraphrased where marked.

Categories: **[WAI]** reported as bug, closed as works-as-intended / expected behaviour. **[CODE]** deliberate-looking-wrong code with a documented reason. **[REVERT]** an "obvious fix" that broke things and was reverted or partially rolled back.

---

## 1. PHP DateTime `+1 month` overflows (Jan 30 + 1 month = March) [WAI]
- Source: https://bugs.php.net/bug.php?id=79916 (status: Not a bug). Related: https://bugs.php.net/bug.php?id=44073
- Looks wrong: `modify('+2 months')` and `modify('+1 month')->modify('+1 month')` give different dates (2020-03-30 vs 2020-04-01).
- Why intended: DateTime does not remember the original day. Each step moves the month forward. If the day doesn't exist in that month, it rolls over into the next one. Maintainer (requinix): "DateTime doesn't 'remember' the original date ... If you say +1 month again then the date moves forward one month *from that point*."
- Over-eager fix: clamp to end of month inside a generic `addMonths()` helper. That breaks callers that rely on PHP/strtotime semantics, and it breaks round-trip identities that existing tests assert.
```php
$d = new DateTimeImmutable('2020-01-30');
$d->modify('+2 months');                    // 2020-03-30
$d->modify('+1 month')->modify('+1 month'); // 2020-04-01  (by design)
```

## 2. Carbon `addMonth()` overflow vs `addMonthNoOverflow()` [WAI]
- Source: https://github.com/briannesbitt/Carbon/issues/2173 (closed, labelled duplicate. Maintainer points to the docs and #1335)
- Looks wrong: `Carbon::create(2020,8,31)->addMonth()->startOfMonth()` gives 2020-10-01, not 09-01.
- Why intended: kylekatarnls: "The overflow behavior is inherited from the PHP `DateTime` class". Carbon offers `addMonthNoOverflow()` / `addMonthsNoOverflow()` as the opt-in alternative and deliberately leaves the default alone.
- Over-eager fix: replace every `addMonth()` with `addMonthNoOverflow()`, or change the default. Billing code that uses overflow intentionally (e.g. "31st + 1 month" meaning "30 or 31 days later") changes behaviour silently.
```php
Carbon::create(2020, 8, 31)->addMonth();           // 2020-10-01 (overflow, intended)
Carbon::create(2020, 8, 31)->addMonthNoOverflow(); // 2020-09-30 (explicit opt-in)
```
- Related reading: https://github.com/briannesbitt/Carbon/issues/2604. `diffInMonths` "inconsistency" is closed. The maintainer says "I doubt we could improve this without degrading other cases."

## 3. `date('Y-W')` returns "2014-01" for 2014-12-29: ISO year `o` vs calendar year `Y` [WAI]
- Source: https://bugs.php.net/bug.php?id=70314 (Not a bug. Explained by Rasmus Lerdorf)
- Looks wrong: 29 Dec 2014 reports week 01.
- Why intended: ISO-8601 week date. If 31 Dec falls on Mon/Tue/Wed, that week is week 01 of the *next* ISO year. `W` must be paired with `o`, not `Y`.
- Over-eager fix: "correct" a week-key builder that uses `'o-W'` to `'Y-W'` because "o is a typo". That produces 2014-01 for late December and merges two different weeks into one bucket (weekly reports, payroll weeks).
```php
date('o-\WW', strtotime('2014-12-29')); // 2015-W01  correct ISO key
date('Y-\WW', strtotime('2014-12-29')); // 2014-W01  wrong: collides with Jan 2014
```

## 4. Carbon `setYear(2021)->isoWeek(1)` returns a 2020 date [WAI]
- Source: https://github.com/briannesbitt/Carbon/issues/2911 (label: "expected behavior")
- Why intended: 2021-01-01..03 belong to ISO week-year 2020. `setYear()` sets the Gregorian year, not the ISO week-year. The maintainer recommends `setIsoDate($year, $week)`.
- Over-eager fix: "normalising" `setIsoDate()` into `setYear()->isoWeek()`. That breaks for years whose 1 Jan is Fri/Sat/Sun.
```php
Carbon::parse('2021-01-01')->setIsoDate(2021, 1); // 2021-01-04 (Monday of ISO week 1)
```

## 5. PHP `round(0.285, 2)` returns 0.29 even though the float is 0.28499999... [WAI, change rejected]
- Source: https://wiki.php.net/rfc/change_the_edge_case_of_round (Declined 7:8, Dec 2023). Also https://www.php.net/manual/en/migration84.other-changes.php
- Looks wrong to a float purist: the IEEE value is below .285, so "correct" rounding gives 0.28.
- Why intended: the 2008 rounding RFC decided "the user expects round() to behave as if the numbers were stored as decimals". The proposal to make round() pure-FP was voted down. (PHP 8.4 separately removed internal "pre-rounding" but kept decimal-intent semantics.)
- Over-eager fix: replacing `round($x, 2)` with a "more precise" custom implementation (`floor($x*100+0.5)/100` or similar) changes cent values on invoices.
```php
round(0.285, 2);               // 0.29 (decimal intent, kept on purpose)
floor(0.285 * 100 + 0.5) / 100; // 0.28  <- "fix" that changes totals
```

## 6. `floor((0.1+0.7)*10)` is 7 [WAI]
- Source: https://bugs.php.net/bug.php?id=28414 (Not a bug). See also https://www.php.net/manual/en/language.types.float.php
- Relevance for a benchmark: code that deliberately keeps money in **integer minor units** or strings, and avoids `floor()`/`(int)` on float products, looks over-engineered. An agent "simplifying" it to float math introduces this bug.
```php
(int) ((0.1 + 0.7) * 10); // 7, not 8
```

## 7. WooCommerce rounds VAT **half-down** when prices include tax [CODE + WAI, still contested]
- Source code: https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/wc-core-functions.php (`wc_get_tax_rounding_mode`, since 3.2.4)
- Maintainer reason: https://github.com/woocommerce/woocommerce/issues/20440. mikejolley: "We round tax half down for tax inclusive prices. HMRC allows either way so long as it's consistent. Rounding .5 up all time causes issues when the tax and the price ends with .5."
- Counterpoint (open): https://github.com/woocommerce/woocommerce/issues/51212 says this is wrong for Finland/EU at 25.5% VAT. The workaround is the `WC_TAX_ROUNDING_MODE` constant, not changing the default.
- Over-eager fix: change `PHP_ROUND_HALF_DOWN` to `HALF_UP` "because tax always rounds up". Gross prices then stop reconciling: net + tax ends up 1 cent above the shelf price on .5 edges, and historical orders become non-reproducible.
```php
function tax_rounding_mode(): int {
    if (WC_TAX_ROUNDING_MODE === 'auto') {
        return get_option('woocommerce_prices_include_tax') === 'yes'
            ? PHP_ROUND_HALF_DOWN   // intentional
            : PHP_ROUND_HALF_UP;
    }
    return (int) WC_TAX_ROUNDING_MODE;
}
```

## 8. WooCommerce rounds per **line**, not per **item** (3 x 12.974 = 38.92) [WAI]
- Sources: https://github.com/woocommerce/woocommerce/issues/18710 (mikejolley: "it's actually calculating correctly given we round per line ... We either do per-line rounding, or subtotal rounding. We do not support per-item rounding ... HMRC ... allow per line or per item rounding. Both are valid as long as you're consistent.") and https://github.com/woocommerce/woocommerce/issues/29657 (rrennick: "In both cases the prices are being rounded per line").
- Over-eager fix: round the unit price before multiplying by qty. That changes the tax base, so the cart, stored orders and refunds disagree by 1 cent. The inconsistency rule is the real requirement.
```php
$lineTotal = round($unitPrice * $qty, 2);   // intended (per line)
// $lineTotal = round($unitPrice, 2) * $qty; // "fix": per item, breaks consistency
```

## 9. WooCommerce keeps internal precision = display decimals + 2 [CODE]
- Source: `wc_get_rounding_precision()` in https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/wc-core-functions.php. Docblock: "This is different from the number of decimals used for display ... if you choose to decrease, there maybe side effects such as off by one rounding errors for certain tax rate combinations."
- Related complaint: https://github.com/woocommerce/woocommerce/issues/24619 (5 x 8.36 net shown as 41.81). A commenter calls it "not a bug ... use the original price in any calculations not the rounded price". The proposed patch author withheld it because it "may break tax calculations for some".
- Over-eager fix: round intermediate values to 2 dp "to match what the customer sees". That accumulates rounding error across tax rates.
```php
$precision = max(wc_get_price_decimals() + 2, WC_ROUNDING_PRECISION); // internal only
```

## 10. Magento: per-row tax 39.92 / 39.91 / 39.92 for three identical items [WAI]
- Source: https://github.com/magento/magento2/issues/24668 (closed). FreekVandeursen: with "calculate tax on order total", the total tax 119.7479 is distributed over rows for display. Showing 39.92 on every row "would mean that the totals do not match". engcom-Bravo: "it's not a problem with Magento - it's a pure Math." Reporter: "Good point. I did not think about that."
- Over-eager fix: make every row's tax identical (round each row). The sum of row taxes then no longer equals the order tax, and the invoice fails reconciliation.
```php
// distribute rounded total across rows, carrying the remainder ("delta")
$delta = 0.0;
foreach ($rows as $r) {
    $exact = $totalTax * $r->share + $delta;
    $r->tax = round($exact, 2);
    $delta  = $exact - $r->tax;   // intentional carry
}
```

## 11. Sylius `IntegerDistributor`: units of the same item get 65.84 and 65.83 tax [WAI/design]
- Source: https://github.com/Sylius/Sylius/issues/4454 ([RFC] Double-rounding on tax). michalmarcinkowski: "our current logic rounds only once ... the distributor will split this value into 2, so one Unit will receive 65.84 and the second unit will receive 65.83, so the tax total will be equal to [the rounded line tax]." The reporter needed per-unit equality for their own business rule. The answer was a custom applicator, not a core change.
- Over-eager fix: give each unit `round(total/qty)`. The unit taxes then no longer sum to the line tax.
```php
// split 13167 cents over 2 units -> [6584, 6583]
$base = intdiv($amount, $n); $rem = $amount % $n;
$parts = array_fill(0, $n, $base);
for ($i = 0; $i < $rem; $i++) $parts[$i]++;
```

## 12. moneyphp `allocate()`: remainder cents go to specific parties, and order matters [WAI/design]
- Docs: https://www.moneyphp.org/en/stable/features/allocation.html. "The remainder fractions are allocated one by one to the targets, the one with most lost due the rounding-down in previous step now coming first." 5 cents at 70/30 gives 4/1, at 30/70 it gives 2/3. `allocateTo(3)` of 8.00 gives 2.67, 2.67, 2.66.
- Report: https://github.com/moneyphp/money/issues/506 (expected 821.50/156.09 from naive rounding, got 821.51/156.08). Also https://github.com/moneyphp/money/issues/258 (negative-amount allocation semantics).
- Over-eager fix: replace allocation with `round(total * ratio)` per party. The parts no longer sum to the total, so money is created or lost.

## 13. brick/money throws `RoundingNecessaryException` instead of silently rounding [WAI]
- Source: https://github.com/brick/money/issues/87. BenMorel: the rounding mode passed to `of()` "only applies to the amount given as first parameter; it is not stored in the Money object, and every subsequent operation will still use RoundingMode::UNNECESSARY unless specified otherwise." Fixed by README clarification only.
- Over-eager fix: wrap in try/catch and fall back to HALF_UP, or store a default rounding mode on the object. That hides precision loss the library forces you to decide on explicitly.
```php
Money::of('4556.235', 'USD');                         // throws, by design
Money::of('4556.235', 'USD', roundingMode: RoundingMode::HALF_EVEN); // explicit
```

## 14. Kimai: 5 minutes at 60/h is billed 4.80, not 5.00 [CODE + partial REVERT]
- Sources: https://github.com/kimai/kimai/issues/5700 ("regression in release 2.41"). Docs: https://www.kimai.org/documentation/rounding.html
- Change in 2.41: `$rate = $hourlyRate * round($seconds / 3600, 2, PHP_ROUND_HALF_UP);`
- Why intended: kevinpapst: "The intention is to make time (with base 60) compatible with money (base 100) ... the smallest unit is 0.01, which equals to 36 seconds." Docs: "Showing 0.08h on the invoice, but charging 5€ is incorrect", because it can "lead to automatic validation errors in e-invoices". DECIMAL mode guarantees "displayed duration x hourly rate = total rate".
- Fallout: users with fractional-hour invoicing were broken (same thread, and https://github.com/kimai/kimai/issues/6213 mentions "classic" mode being "reintroduced"). The project kept DECIMAL and added CLASSIC as a setting. It did not simply revert.
- Over-eager fix: remove the `round(..., 2)` "because it loses precision". That re-breaks EN 16931 / e-invoice quantity x price = line total validation.

## 15. Dolibarr: a validated invoice can only be deleted if it is the *last* number (plus other conditions) [CODE, legal]
- Source code: https://github.com/Dolibarr/dolibarr/blob/develop/htdocs/core/class/commoninvoice.class.php (`is_erasable()`, read from raw file). Docblock rules: in bookkeeping gives -1. "has a definitive ref, is not last in ref" gives -2. Any payment gives -4. Sent by email gives -5. Printed gives -6. Certified (LNE) version gives -7. Drafts with provisional `(PROV…)` refs are always erasable. `INVOICE_CAN_NEVER_BE_REMOVED` disables deletion entirely.
- Why: gap-free sequential invoice numbering and immutability of issued invoices, required by e.g. French invoicing / anti-fraud rules. Corrections go through credit notes. Final numbers are assigned only at validation, so drafts never burn numbers. (The legal background was not found in a Dolibarr issue thread. It comes from general knowledge plus the code comments.)
- Over-eager fix: "allow deleting any unpaid invoice" or "assign the final number at draft creation". The first creates numbering gaps, the second burns numbers when drafts are discarded.
```php
if ($status === DRAFT && str_starts_with(substr($ref, 1), 'PROV')) return 1;
if ($ref !== $this->getLastNumber()) return -2;   // only the last one, keeps sequence gap-free
```

## 16. Firefly III: every journal stores a -X and a +X row (sign convention) [CODE/design]
- Source: https://docs.firefly-iii.org/explanation/financial-concepts/transactions/. "Each journal contains two 'transactions'. One takes money (-250 from your bank account) and the other one puts it into another account (+250 for Amazon.com)." "There are always twice as many 'transactions' as there are 'journals'."
- Over-eager fix: "dedupe" the duplicate rows, `abs()` amounts, or sum all transaction rows to get "total spent" (that gives 0). Double-entry invariants break: per-journal sum must be 0.
```php
assert(array_sum(array_column($journal->transactions, 'amount')) == 0);
```

## 17. Laravel: a "fix" that made `date_format` validation strict was reverted [REVERT]
- Original "fix": https://github.com/laravel/framework/pull/16692 "[5.3] Fix date_format validation for all formats". It rejected `16-01-01` for `Y-m-d` by re-formatting and comparing.
- Revert: https://github.com/laravel/framework/pull/16845. "Revert #16692 and make date_format work with ISO8601 again". `1991-07-03T12:00:00Z` against `Y-m-d\TH:i:sP` stopped validating. Taylor Otwell: "Reverting this since we can't have any breaks on a patch release."
- Lesson for the benchmark: "strict round-trip" checks (`format(createFromFormat($f,$v)) === $v`) look obviously more correct. They reject valid inputs whose canonical rendering differs (Z vs +00:00).
```php
$d = DateTime::createFromFormat('Y-m-d\TH:i:sP', '1991-07-03T12:00:00Z');
$d->format('Y-m-d\TH:i:sP'); // '1991-07-03T12:00:00+00:00' !== input -> strict check rejects valid ISO-8601
```
- Similar: https://github.com/laravel/framework/pull/38993 reverts #38720 ("Compare custom date/immutable_date using date comparison" for dirty checking). The revert PR body gives no reason.

## 18. PHP 8.0 stable sort and the `bool`-returning comparator fallback [CODE/compat]
- Source: https://wiki.php.net/rfc/stable_sorting. PHP 8.0 made all sorts stable. Comparators returning `bool` are deprecated. When `false` is returned, PHP re-calls the comparator with swapped args to tell "equal" from "less".
- Benchmark angle: code that adds an explicit tiebreaker (e.g. original index or id) to a usort comparator looks redundant on PHP 8. It is needed for deterministic ordering across versions (<8.0) or ORMs. Removing it makes invoice line ordering and FIFO stock consumption order non-deterministic.
```php
usort($lots, fn($a, $b) => [$a->date, $a->id] <=> [$b->date, $b->id]); // id tiebreak = FIFO determinism
```

---

### Not verified / dropped
- WooCommerce negative stock: https://github.com/woocommerce/woocommerce/issues/25518 was opened, but it is a *confirmed bug* (manual order creation ignores the backorder setting), not intended behaviour. Do not cite it as by-design.
- Dolibarr wiki https://wiki.dolibarr.org/index.php/FAQ_Facture_EN only describes manual DB deletion. It has no legal rationale.
