# PAY-77: payment states and PSP commands are wrong in production

Since we moved the payments topic to the new event bus, orders show
"failed" although the money was captured, refunds are sent twice or never,
and some authorisations are never voided (customers see blocked amounts).
The projector was written assuming events arrive once and in order.

Make `App\Payments\OrderProjector` correct according to `docs/EVENTS.md`.
Keep its public API. PHP 7.4, no dependencies. `php tests/run.php` must
pass; add tests.
