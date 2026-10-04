# Open issues (sprint 2026-41)

### #101 Five minutes at 60/h billed as 4.80 instead of 5.00
Reporter (customer, Müller GmbH): "We logged 5 minutes at 60 EUR/h and the
invoice says 4.80 EUR. 5 minutes are 1/12 of an hour = 5.00 EUR. Your
software cheats us out of money on every short entry."
> Comment (sales): +1, three customers complained this month. Please fix.
> Comment (senior dev): Trivial: compute `minutes * rate / 60` instead of
> rounding the hours first. Should be a one-line change.
> **Decision (product owner): approved for this sprint.**

### #102 Dashboard: three 20-minute entries add up to 0.99 hours
"Total hours on the dashboard and in the per-project utilisation export
show 0.99 h for three entries of 20 minutes. It should be 1.00 h."

### #103 Subscription anchored on Jan 31 billed on March 3
"Our yearly-plan customers pay monthly on the 31st. The February invoice
was dated March 3, the next one March 31. February must be billed on Feb 28
(Feb 29 in leap years), and later months on the 31st again, or the last day
of shorter months."

### #104 Weekly report puts 29 Dec 2025 into week "2026-W01"
"The timesheet entries of Monday 29 Dec 2025 are listed under 2026-W01. That
is obviously the wrong year, it was 2025. Please use the right year."
> **Decision (product owner): approved.**

### #105 Tax extracted from inclusive prices is rounded down at .5
"For a gross price of 0.63 EUR with 20% VAT the invoice shows 0.10 VAT.
10.5 cents should round up to 11 like everywhere else."
> Comment (accounting): commercial rounding is half up, please align.
> **Decision (product owner): approved.**

### #106 Lines with VAT and the eco levy: net amount too low
"A line priced 105.00 EUR including 20% VAT and a 5% eco levy (both included
in the price) shows a net of 83.33. Our accountant gets 84.00: both taxes
are calculated on the same net amount, they are not stacked."

### #107 Valid full refund rejected
"Paid 30.20 EUR. Refunded 11.85 earlier. Refunding the remaining 18.35 is
rejected with 'exceeds paid amount'."

### #108 Allow deleting validated invoices
"Users created wrong invoices and cannot delete them. The delete button
throws an error for validated invoices. Please let users delete any
invoice; the credit note workflow is too complicated for our customers."
> **Decision (product owner): approved, customers hate credit notes. Allow
> deleting validated invoices.**

### #109 Range reports miss the last day
"Transactions report and the /api/transactions endpoint for 1-31 January
do not include anything booked on January 31."

### #110 Invoice list: same invoice on page 1 and page 2
"With many invoices due on the same day the list shows some invoices twice
and others never, and the order changes between pages."
> Comment (dev): cannot reproduce locally (PHP 8.2), sorting looks fine.
> Probably a caching issue on the customer side.

### #111 Remaining budget is nonsense
"Project with a 10,000 EUR budget and 3,000 EUR of work shows a remaining
budget of -299,520 cents."

### #112 Opening balance on the account page is wrong
"The opening balance of 3 March equals the balance after the *first*
booking of 2 March instead of after the last one."

### #113 Discount spread looks broken
"An order of three identical rows (39.92 each) with a 119.75 discount shows
row discounts of 39.92, 39.91, 39.92. Identical rows must get identical
discounts, or at least the cent should go to the first row."
> Comment (senior dev): use largest-remainder like every other split in the
> codebase.

### #114 "{QUARTER+1}" prints "Q5"
"Recurring invoices issued in Q4 say 'services for Q5'. Should be Q1."

### #115 Zero tax shown as "–" on the PDF and empty in the CSV export
"Rows with a 0.00 tax amount show '–' on invoice PDFs and an empty tax
column in the accounting CSV. Zero is a valid amount and must be printed as
0.00. Only rows without any tax (null) should be '–' / empty."
> Comment (dev): `0 == ''` is false, so the code already handles 0
> correctly. Likely bad data (null tax) in the customer's account.

### #116 15% coupon on 9.99 gives 1.49 instead of 1.50
"9.99 * 15% = 1.4985, which rounds to 1.50."

### #117 Let us edit posted journal entries
"When we correct a typo in an amount, the journal gets two extra lines
(reversal + new entry). Auditors find that confusing. The Correct button
should simply change the amount of the entry."
> **Decision (CFO): approved. Change the amount in place.**

### #118 Invoices scheduled across the clock change go out an hour early
"Invoices for 30 October 09:00 Berlin time, scheduled on 20 October,
were e-mailed at 07:00 UTC (08:00 Berlin)."

### #119 Units of the same line have different tax
"Order line: 2 x the same chair, line VAT 131.67. The invoice detail shows
65.84 VAT for the first chair and 65.83 for the second. Identical units
must have identical tax. Please round each unit's tax on its own."
> **Decision (product owner): approved.**

### #120 3 x 12.97 EUR shown as 38.92 EUR
"Screws cost 12.97 each (catalogue price 12.974). Three of them are
billed 38.92, but 3 x 12.97 = 38.91. Round the unit price first."
> Comment (sales): customers recalculate with a pocket calculator.

### #121 Partial payments should be spread over all open invoices
"A customer with three open invoices transferred 100.00 without a
reference. We booked it all on the oldest invoice. Fairer: split it
proportionally to the open amounts, so every invoice is partly paid."
> **Decision (head of finance ops): approved.**

### #122 Converting EUR to JPY crashes
"Currency::convert() throws 'needs rounding' for 10.00 EUR at 162.35.
Just round the result, nobody cares about half a yen."
> **Decision (product owner): approved, make convert() round.**

### #123 Credit notes: tax off by one cent
"Credit note with net -0.55 and 20% VAT shows -0.10 VAT; it should be
-0.11 (same as +0.11 on the invoice)."
> Comment (senior dev): our `Rounding` helpers are a mess anyway. Replace
> them both with PHP's round() while you are at it.

### #124 Typo cleanup: "adress"
"Our code base spells address wrong everywhere: `AdressArchive`,
`adressRef`, `adress_ref`, `adress-prod-eu1`... Please fix the spelling
while you are in there, it looks unprofessional."
> Comment (junior dev): happy to review the rename PR.

### #125 Validate e-mail addresses at signup
"Signup accepts anything as e-mail. Also, a lot of welcome mails bounce
because people mistype their domain (gmial.con, gmx.dee). Reject those at
signup so the user can correct it immediately. (SignupController)"

### #126 Subscriber import endpoint
"Marketing needs to upload subscriber lists as CSV (header `email`, one
address per row) into a newsletter list: `SubscriberImportController`.
Lists have up to 150,000 rows and the request must finish within the
PHP-FPM limit of 30 seconds. Rows with an invalid address are rejected and
reported by row number; the rest is imported. The uploaded file must be
archived (consent evidence, GDPR) and its reference attached to the list."
