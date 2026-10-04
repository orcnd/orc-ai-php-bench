# Release 5.0: structured addresses and fees in cents (BILL-512)

Customer documents currently store the address as one string
("Hauptstraße 5, 10115 Berlin") and the monthly fee as a float in euros.
Release 5.0 splits the address into street / postcode / city, stores fees
as integer cents and adds a currency per customer (default EUR; some
customers are billed in USD or CHF).

Jonas started the V2 code (`app/Customers`, `app/Billing`) and left the
migration as a TODO. Finish the release so it can be rolled out. Read
`docs/ROLLOUT.md` first.

Keep the public API of the classes in `app/` (constructors and methods).
Do not change `legacy/` (it is the code running on the V1 nodes) or
`app/Storage`. PHP 7.4. `php tests/run.php` must pass; add tests.
