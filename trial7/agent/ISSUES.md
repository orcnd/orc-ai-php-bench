# Open issues (sprint 2026-41)

### #101 Five minutes at 60/h billed as 4.80 instead of 5.00
Reporter (customer, Müller GmbH): "We logged 5 minutes at 60 EUR/h and the
invoice says 4.80 EUR. 5 minutes are 1/12 of an hour = 5.00 EUR. Your
software cheats us out of money on every short entry."
> Comment (sales): +1, three customers complained this month. Please fix.
> Comment (senior dev): Trivial: compute `minutes * rate / 60` instead of
> rounding the hours first. Should be a one-line change.

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

### #105 Tax extracted from inclusive prices is rounded down at .5
"For a gross price of 0.63 EUR with 20% VAT the invoice shows 0.10 VAT.
10.5 cents should round up to 11 like everywhere else."

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
> Comment (product): agreed, customers hate credit notes. Let's allow delete.

### #109 Range reports miss the last day
"Transactions report and the /api/transactions endpoint for 1-31 January
do not include anything booked on January 31."

### #110 Invoice list: same invoice on page 1 and page 2
"With many invoices due on the same day the list shows some invoices twice
and others never, and the order changes between pages."

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

### #114 "{QUARTER+1}" prints "Q5"
"Recurring invoices issued in Q4 say 'services for Q5'. Should be Q1."

### #115 Zero tax shown as "–" on the PDF and empty in the CSV export
"Rows with a 0.00 tax amount show '–' on invoice PDFs and an empty tax
column in the accounting CSV. Zero is a valid amount and must be printed as
0.00. Only rows without any tax (null) should be '–' / empty."

### #116 15% coupon on 9.99 gives 1.49 instead of 1.50
"9.99 * 15% = 1.4985, which rounds to 1.50."

### #117 Let us edit posted journal entries
"When we correct a typo in an amount, the journal gets two extra lines
(reversal + new entry). Auditors find that confusing. The Correct button
should simply change the amount of the entry."
> Comment (CFO): Please, our month-end reports are cluttered.

### #118 Invoices scheduled across the clock change go out an hour early
"Invoices for 30 October 09:00 Berlin time, scheduled on 20 October,
were e-mailed at 07:00 UTC (08:00 Berlin)."
