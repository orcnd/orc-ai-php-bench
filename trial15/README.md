# Trial 015: maintainer review

Five issues in one PR, each modelled on a pattern from rejected AI-agent PRs
(see `../research/research_rejected_prs_studies.md` and
`research_rejected_prs_examples.md`):

| Issue | Pattern | Source |
| --- | --- | --- |
| #201 local-pickup tax address | re-deriving a filtered value bypasses plugin overrides | WooCommerce #66653 |
| #202 stale shipping cost | cache keyed by method id only; must still call the carrier once per package | WooCommerce #66211 |
| #203 decline code lost | the issue names 3 gateways, all 5 have the bug | "fixed only the named cases" (dotnet/runtime Copilot PRs) |
| #204 coupon expiry | timezone/DST via the injected Clock | flaky-time PRs |
| #205 CSV export | quoting beyond `"` plus formula injection, amounts untouched | symfony #66279 |

Scoring: functional 25, completeness 20, invariants 15, ci 15 (`bin/ci` on
PHP 7.4 with the bundled PHPStan; changing CI files voids it), conventions 10
(CONTRIBUTING rules checked by pattern, only new violations count), tests 10
(mutants killed by the PR's own tests), discipline 5 (existing tests
untouched, new test file, no edits to unrelated existing files). CSV checks
parse the output, so any valid RFC 4180 quoting passes.
