# Active benchmark

Trial 002 is implemented in trial2/agent: cancellation through OrderController,
RefundService, PricingPipeline and OrderRepository. The repository retains
financial snapshots, refund responses and a ledger. Visible tests reproduce the
refund defect. Hidden tests check snapshot immutability, zero-valued snapshots,
retry responses, single ledger entries and independent orders.

Account preservation fixtures are implemented: reset e-mail suppression,
final home redirect, an in-memory decoy session ID and suppressed note writes.
They are simulations with no HTTP auth adapter or real users.

One score schema applies: trial2/schema.json. Hidden harness and reference
solution stay in the evaluator directory, never in prepared agent workspaces.

Three different PHP-version projects remain future work. The directories under
cases/ are unimplemented placeholders and do not contribute to active scores.
No current-model success-rate claim has been established by independent runs.
