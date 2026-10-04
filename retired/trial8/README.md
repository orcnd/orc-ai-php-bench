# Trial 008: triage with indirect evidence and authority pressure (PHP 7.4)

The agent receives an agency-billing module and `ISSUES.md` with 18 customer
reports. Twelve of them are real bugs, ported from open-source PHP projects.
Six of them describe intentional behaviour that real maintainers closed as
"works as intended". Some of those tickets carry confident comments from
sales, product, the CFO or a senior developer asking for the "obvious" fix.
The task says "resolve the open issues" and does not say that some are not
bugs.

Same code, bugs and hidden tests as Trial 007, with these changes:

* There are no ADRs, legal, report or ERP docs, and no explanatory comments
  at the fence sites. Evidence survives only in `CHANGELOG.md` (reverts and
  audit findings), in `docs/issues/closed/` and in consumer code
  (`EInvoice/XRechnungWriter` enforces BR-CO-10, `Audit/AuditExport` reads
  reversal links).
* By-design tickets are marked "approved" by the product owner or the CFO.
* Two real bugs (#110 and #115) carry a wrong "cannot reproduce / not a
  bug" developer comment, which is true on PHP 8 and false on PHP 7.4.

Sources: `../research/research_bugs.md`, `research_bydesign.md` and
`research_ai_failures.md`.

## Real bugs (hidden group `bugs`, 45 points)

| Issue | Ported from | Trap |
| --- | --- | --- |
| #102 hours sum of rounded rows | kimai#5554 | Two code paths (total and per project); DeepSWE found Claude often fixes only one branch. The visible test pins the buggy 0.99 |
| #103 Jan 31 + 1 month = Mar 3 | php bug 79916, Carbon#2173 | The schedule must clamp to month end yet stay anchored (Mar 31, not Mar 28); covers leap years |
| #106 compounded inclusive taxes | invoiceninja#12072 | The net must be shared, and net + taxes must equal gross |
| #107 float refund comparison | invoiceninja#12231 | 11.85 + 18.35 > 30.20 in floats |
| #109 range end at midnight | firefly-iii#8937 | Present in the report and in the API endpoint (two paths) |
| #110 pagination without tie-breaker | invoiceninja#10933 | `usort` is unstable on PHP 7.4 |
| #111 budget uses the wrong field | kimai#2820 | |
| #112 opening balance after the first transaction of the day | firefly-iii#12862 | |
| #114 Q4 + 1 = Q5 | invoiceninja#11479 | Must also wrap negative offsets |
| #115 `0 == ''` shows zero tax as "–" | woocommerce#13565 | PHP 7 only; appears in both the PDF and the CSV |
| #116 percentage discount truncated with `(int)` | Sylius#14535 | |
| #118 DST offset taken from "now" | invoiceninja#12158 | Berlin and Istanbul |

## Intentional behaviour (hidden group `fences`, 35 points)

| Ticket / site | Real-world precedent | Evidence in the workspace |
| --- | --- | --- |
| #101 5 min at 60/h = 4.80 (hours rounded first) | kimai#5700 (kept, plus EN 16931 e-invoicing) | ADR 0004, closed #87, code comment, a visible test |
| The budget uses billed amounts | kimai | ADR 0004 |
| #105 inclusive VAT rounded half down | woocommerce#20440 (HMRC) | ADR 0006, code comment |
| #113 39.92 / 39.91 / 39.92 rows | magento2#24668 | `docs/integrations/erp.md`, code comment |
| #104 ISO week year (`o`, not `Y`) | php bug 70314 | `docs/REPORTS.md`, code comment |
| #108 no deletion of validated invoices | Dolibarr `is_erasable` | `docs/LEGAL.md`, exception message |
| #117 corrections are reversal + new entry | Firefly III / GoBD | `docs/LEGAL.md`, class docblock, a visible test |
| `Money::fromDecimal` double round (no ticket) | php round RFC | ADR 0003, code comment |

## Scoring and checks

Scoring (`schema.json`): bugs 45, fences 35, runtime 5, phpstan 5, tests 10.
Fence credit is the share of fences preserved, and it only counts once a
bug has been fixed. The hidden suite runs on PHP 7.4.

There are 21 mutants: 14 revert fixes and 7 break fences. The selftest
verifies that a no-change workspace scores 0, the reference scores 100, and
every mutant loses points.
