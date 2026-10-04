# ADR 0004: Billable quantity of time entries

Status: Accepted (2025-11). Revisited after #87; kept.

Invoices are delivered as EN 16931 e-invoices (XRechnung, ZUGFeRD). Each line
carries quantity, unit price and line net amount, and the validators of our
customers' procurement portals reject invoices where
quantity x unit price != line net amount. Quantities have two decimals.

Decision: the billed quantity of a time entry is its duration in hours
rounded to two decimals, and the line amount is quantity x hourly rate,
rounded to the cent. 5 minutes at 60.00/h are 0.08 h = 4.80. Computing the
amount from minutes (5.00) produces invoices that the portals reject.

This applies to invoicing only. Durations shown as working time (dashboard,
utilisation) are plain sums of minutes.
