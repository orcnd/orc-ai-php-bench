# Trial 016: rejected AI pull requests, ported to PHP

Twenty modules, each reproducing a pull request opened by an AI agent
(mostly Copilot) and rejected by maintainers. The cases come from the
AIDev-based analysis in `../research/REJECTED_AI_PRS.md`. The issue text
mirrors the original report. The hidden checks encode what the maintainer
said was wrong. The mutants in `mutants.json` replay what the original agent
did.

| Case | Original PR | Maintainer's objection reproduced |
| --- | --- | --- |
| c01 tokenizer | phiki/phiki#76 | a zero-width match must be skipped and the next rule tried, not just "break the loop" |
| c02 merger | Azure/azure-service-operator#4864 | explicit false/0/'' overrides; lists replace, maps merge |
| c03 correlations | camunda/camunda#37452 | keep all correlations, keyed by (message, subscription), idempotent |
| c04 version contract | dotnet/msbuild#12591 | fix the caller; never change the public tryParse contract |
| c05 xdg | Homebrew/brew#20658 | new config location must not strand existing users |
| c06 schema | opsmill/infrahub#6767 | new validation is a warning unless strict, so existing schemas keep loading |
| c07 discovery | microsoft/vscode#268211 | challenge URL is the only candidate |
| c08 search | ArcadeData/arcadedb#2587 | deduplicate before paginating |
| c09 export | benwbrum/fromthepage#4811 | verbatim keeps the marker; reading text joins the word |
| c10 rewriter | adamchainz/django-upgrade#598 | never rewrite forms you do not understand |
| c11 suppression | marimo-team/marimo#3806 | comments and `#`/`;` inside strings |
| c12 top-n | mabel-dev/opteryx#2860 | push LIMIT down only as far as the sort key allows |
| c13 validation | dotnet/aspnetcore#62623 | object-level validation only after property rules pass |
| c14 barcodes | lakasir/lakasir#328 | soft deletes; restoring must not create two active owners |
| c15 deprecations | magicbug/Cloudlog#3335 | fix every file, not only the ones named in the issue |
| c16 rpki | cosh RPKI maxLength PR | absent maxLength means exact prefix; malformed ROAs discarded; AS0 never matches; invalid vs not-found |
| c17 savemode | NVIDIA/spark-rapids `ignore` save mode | `ignore` is a no-op when the table exists (data not even read), not "ignore errors"; Spark mode names are case-insensitive |
| c18 subjects | fromthepage duplicate subject titles | dedupe per (collection, category) on a Unicode-normalised title; keep the first spelling |
| c19 workflow | microsoft/autogen conditional edges | a skipped edge resolves the join; skips propagate through steps that never run; joins still wait for every taken branch |
| c20 redirects | cosh `>>` redirect marker | `>>`/`2>>`/`1>>`, attached or not; quoted and escaped `>` stay literal; last redirect wins |

Scoring: 4.5 points per case (90), plus runtime 5 and phpstan 5. A case
scores 0 if any of its checks that passed on the pristine code now fails
(the regression rule); otherwise it earns credit for newly passing checks.
