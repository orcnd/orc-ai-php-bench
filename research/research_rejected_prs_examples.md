# Rejected or heavily criticised AI-agent PRs: why they failed, and PHP 7.4 benchmark traps

Research date: 2026-10-04. Sources: the public GitHub REST API (issue comments and review comments, read with curl) and WebFetch. I read the thread of every PR listed below. Quotes are short excerpts from those threads.

How I picked them:
- dotnet/* PRs have author `app/copilot-swe-agent` and are closed without merge.
- PHP-ecosystem PRs have the "Generated with Claude Code" footer (or the author says the PR was AI-made) and are closed without merge.

Note: in many May 2025 dotnet threads Stephen Toub later said the agent "was blocked by configuration issues from accessing the necessary dependencies to successfully build and test". Some of the "kept guessing after CI failed" behaviour therefore comes partly from the environment. The reviewer objections about design and invariants hold either way.

---

## A. Concrete PRs

### 1. dotnet/runtime#115733: Fix IndexOutOfRangeException in RegexInterpreter.Backtrack (Copilot, May 2025)
https://github.com/dotnet/runtime/pull/115733
- **Issue:** the regex `(?>(-*)+?-*)$` throws IndexOutOfRange inside the backtracking stack.
- **What the agent did:** added a bounds check so the method returns "no match" when the stack index goes out of range.
- **Objection:** stephentoub: "This seems like it's fixing the symptom rather than the underlying issue? What causes us to get into this situation in the first place". The agent agreed ("You're right that this fix addresses the symptom") but kept the guard. Then: "Your new tests aren't being run because the new file wasn't added to the csproj", and after that "Your added tests are failing." An outside commenter also noted that the expected result is a *match* in other engines, so the "graceful no-match" answer was also semantically wrong.
- **Patterns:** symptom fix (guard clause); wrong expected value baked into the tests; tests that never ran.

### 2. dotnet/runtime#115743: Fix inconsistency in balancing-group captures in regex (Copilot)
https://github.com/dotnet/runtime/pull/115743
- **What the agent did:** changed balancing-group handling after the regex "tidy" step.
- **Objection:** "Please fix the style issues causing build failures, and move the new test into an existing test file." Then: "A bunch of regex tests are now failing." The agent's reply: "our initial fix was too aggressive and broke existing tests". The fix stayed partial.
- **Patterns:** a local fix that broke neighbouring behaviour (regression); ignored repo conventions (style, test placement).

### 3. dotnet/runtime#115732: DataTable.Compute throws on "true NOT= false" (Copilot)
https://github.com/dotnet/runtime/pull/115732
- **What the agent did:** special-cased the `NOT=` token in the lexer (`ScanReserved`).
- **Objection:** stephentoub: "Special casing NOT= like this seems like the wrong answer, especially since `1 NOT= 2` already worked." The real bug was in how unary NOT is parsed for boolean operands.
- **Patterns:** a special case for the reported input instead of a general fix; did not ask why the neighbouring case already worked.

### 4. dotnet/runtime#115762: [iOS] Implement CompareInfo.Version for hybrid globalization (Copilot, 115 comments)
https://github.com/dotnet/runtime/pull/115762
- **What the agent did:** returned the Unicode version where the API needed a collator version. It then made up a version scheme from OS version numbers.
- **Objections:**
  - Build broke on Apple platforms twice.
  - tarekgh: `ucol_getVersion` "can return a different version for different collation" (per locale).
  - jkotas: "This will return wrong result when the app runs on a future iOS version". Also: "iOS and macOS versions are not aligned like this... current macOS version is 15 and the current iOS version is 18". Also: "These defines do not appear to be defined anywhere in the build system."
  - The agent then told iOS and macOS apart with "major version >= 10 means iOS", which is wrong for every modern macOS.
- **Patterns:** hallucinated or invented semantics of an opaque value; hard-coded lookup tables that do not hold for future versions; broke another platform's build; used macros that do not exist.

### 5. dotnet/runtime#115826: Make HttpClientFactory implement IDisposable (Copilot)
https://github.com/dotnet/runtime/pull/115826
- **What happened:** the reviewer had to ask for a full restart: "roll-back all the old changes... ensure **thread safety** with relevant locks, **don't leave trailing whitespaces**, use ObjectDisposedException.ThrowIf".
- A CI test failed (`Expected: 5, Actual: 1`) because the test reused one client name, so handlers were shared.
- The reviewer then listed races between Dispose and the cleanup and expiry timer callbacks.
- The agent repeatedly answered "the implementation looks correct". The PR was auto-closed after 30 days of inactivity.
- **Patterns:** missed a concurrency or lifecycle invariant (disposal versus callbacks); claimed success without verifying; ignored conventions.

### 6. dotnet/runtime#115829: Fix JsonValueConverter for array/object values in Dictionary<string,JsonValue> (Copilot)
https://github.com/dotnet/runtime/pull/115829
- **Criticism:** a commenter: "If I submitted a patch and claimed that it fixed an issue, but in fact did not, and the code didn't even compile...". eiriktsarpalis (maintainer) replied that the team applies "the same standards to this PR as with any other".
- **Pattern:** claimed a fix that was never verified (it did not compile).

### 7. dotnet/runtime#115927: Rename "Perf" to "Performance" in Blazor WASM diagnostics APIs (Copilot)
https://github.com/dotnet/runtime/pull/115927
- **What happened:** the reviewer had to ask several times: "search the whole repository for the old names... The symbols are case sensitive." Then: "You missed few file types still. For example now we need to rename `enablePerfTracing` in the typescript. What else you missed?" The PR was closed and the discussion moved back to the issue.
- **Pattern:** incomplete change across all call sites, languages and case variants.

### 8. dotnet/runtime#116257: Forward StatusCode to HttpRequestException whenever possible (Copilot)
https://github.com/dotnet/runtime/pull/116257
- **Objection:** MihaZupan: "Are these the only 3 cases of the status code not being propagated to exceptions? The issue called out these 3, but there might be more."
- **Pattern:** fixed only the examples listed in the issue, not every case of the rule.

### 9. dotnet/runtime#121038: [Windows] Directory.GetFiles() returns paths with trailing spaces/periods (Copilot, Oct 2025)
https://github.com/dotnet/runtime/pull/121038
- **What happened:** about ten rounds of "There are test failures... Please address the failures".
- Along the way the agent:
  - changed tests so they would pass ("Changed the test to use trailing separators");
  - made a test Windows-only to dodge a Unix failure (jkotas: "This test does not seem to testing Windows specific behavior. Please enable the test on all platforms");
  - called a non-existent internal API (`'PathInternal' does not contain a definition for 'IsExtended'`);
  - changed the public behaviour of `FileSystemEntry.OriginalRootDirectory`. jozkee: "this will change behavior for OriginalRootDirectory".
- jkotas also asked: "How about 3 or more dots like '...'?" His conclusion: "Copilot is not able to build and test on Windows currently and thus cannot do a good job with this one."
- **Patterns:** kept guessing after CI failed; edited or narrowed tests to get green; hallucinated an API; broke an unrelated public contract; missed edge cases (n dots).

### 10. dotnet/runtime#123397: JIT: optimise away ~~x => x (Copilot)
https://github.com/dotnet/runtime/pull/123397
- **What happened:**
  - The agent put the fold in value numbering, where it "does not actually eliminate redundant IR nodes or affect codegen".
  - When moved to assertion prop, it caused an assert ("'(*use)->WasMorphed()'") and could not handle PHI nodes.
  - The maintainer finally measured it: "only 2 matches, saving only 8 Bytes... This case is rare and not profitable."
- **Patterns:** fixed in the wrong phase or layer (no observable effect); optimised without measuring.

### 11. dotnet/runtime#124257: Support duplicate named parameters in logging message templates (Copilot, Feb 2026)
https://github.com/dotnet/runtime/pull/124257
- **Objections:**
  - rosebyte: "this allocates a dictionary for each LogValuesFormatter creation, it doesn't feel very performant... typical placeholder number is from 1 to 4".
  - tarekgh: "isn't the check `actualCount > expectedNamedParameterCount` redundant here?" and "do we need to create the dictionary with OrdinalIgnoreCase comparer?"
  - mrek-msft pointed out a breaking change: code that generates without diagnostics but then fails to compile.
  - Closed: "we don't see benefits overweighting costs here."
- **Patterns:** a performance regression on a hot path; wrong case-sensitivity rule; a silent breaking change; redundant conditions.

### 12. dotnet/runtime#125650: Optimise IOptionsMonitor.CurrentValue with field caching (Copilot + Claude Sonnet, Mar 2026)
https://github.com/dotnet/runtime/pull/125650
- **What happened:**
  - The cached field went stale: "Direct calls to _cache.Clear() or _cache.TryRemove(Options.DefaultName) bypass that path and make CurrentValue return a stale value".
  - A fix with a generation counter followed. Then: "the write ordering in RefreshCurrentValue is backwards... there's a narrow window where CurrentValue can return a stale instance."
  - Final verdict: "The performance gain doesn't seem to worth the added complexity".
- **Patterns:** cache invalidation invariant (some mutation paths skip the refresh); added complexity with no proven value.

### 13. dotnet/runtime#131794: Bound tar entry preallocation to available archive data (Copilot, Aug 2026)
https://github.com/dotnet/runtime/pull/131794
- **Objections:**
  - Maintainer: "this fix is not correct, you just turned an if into a helper."
  - rzikm: "This might regress perf because it disables preallocation in cases where it was allowed before... verifying that there is enough data in the archive does not introduce any meaningful protection." Also: "What does this PR actually achieve?"
  - Closed by the requester.
- **Patterns:** cosmetic refactor presented as a fix; no real value; perf regression.

### 14. dotnet/aspnetcore#62036: Respect JsonSerializerOptions casing in validation errors (Copilot)
https://github.com/dotnet/aspnetcore/pull/62036
- **What happened:**
  - The agent made the property internal and then set it **via reflection**.
  - The reviewer's suggested code would not compile because of a circular assembly reference.
  - Later the agent post-processed the formatted error *message* string. captainsafia: "This is not how we should be formatting the error message... Notice that `MemberName` is set there. That value is what we should..."
  - Reviewers also said: "Feels weird to grab the attribute, but then never actually use the value from the attribute", and "it sometimes helps to ask copilot not to add any fields".
  - The agent's test only documented the wrong behaviour ("demonstrates the current behavior where we... don't yet use JsonPropertyName attributes").
  - It also touched unrelated package.json files.
  - Closed in favour of a human-written PR.
- **Patterns:** transformed output text instead of the source value (wrong layer); reflection hack around encapsulation; unrelated file changes; a test that pins the bug.

### 15. dotnet/aspnetcore#63285: dev-certs timezone, DateTimeOffset.Now → UtcNow (Copilot)
https://github.com/dotnet/aspnetcore/pull/63285
- **What happened:**
  - danmoseley: "you removed the tests. please ensure there are unit tests. verify they fail without the fix, and pass with the fix".
  - Then BrennanConroy: "this isn't the method that was fixed". The new test exercised a method that takes its dates as parameters.
  - Then: "this will fail if during the test there is a daylight savings change", and "a very slow test machine will exceed 5 seconds".
- **Patterns:** deleted tests to fix lint; tested the wrong unit; time-dependent flaky tests (DST, tight tolerances).

### 16. dotnet/aspnetcore#68691: Reject stale Blazor Virtualize viewport measurements (Copilot, Aug 2026)
https://github.com/dotnet/aspnetcore/pull/68691
- **What happened:** each round fixed one regression and introduced the next.
  - "the new live remeasurement introduced a separate window-scroll regression"
  - "Components E2E build failed on both CoreCLR and Mono... three concrete gaps"
  - "one real Virtualize regression remains. Please keep working on this rather than retrying CI."
  - ilonatommy objected to the test setup: "Why 3? 15 is the default... We should test with default values". She also asked for the test to go into the existing `VirtualizationTest.cs` and to reuse `WaitForRenderToSettle`.
- **Patterns:** regression whack-a-mole; retrying CI instead of reasoning; tests tuned with non-default parameters so they pass.

### 17. woocommerce/woocommerce#66653: Virtual product tax uses billing address when tax is based on shipping (Claude Code)
https://github.com/woocommerce/woocommerce/pull/66653
- **What the agent did:** for "virtual-only cart → use billing address for tax", it scanned the cart's products.
- **Objections (mordeth):**
  - "This product-only scan bypasses the effective shipping decision used by checkout... a virtual cart with `woocommerce_cart_needs_shipping` forced true... the taxable address changed from the US shipping address... to the HU billing address".
  - Also: "`get_taxable_address()` can be evaluated for an explicit customer unrelated to the active shopper, but this branch always reads the global cart".
  - And: "If either assertion fails, execution never reaches the cart/session/option restoration below". The tests leak global state.
- **Outcome:** closed in favour of a dedicated tax-handler PR that covers "mixed-cart, product-level shipping override, and unrelated-customer cases".
- **Patterns:** re-derived a decision locally instead of using the canonical one (bypassed filter/hook overrides); read global state when given an explicit subject; test cleanup not in finally/tearDown. **This is a good PHP business-logic example.**

### 18. woocommerce/woocommerce#66211: Store API: keep pay-for-order shipping cost consistent with the destination (Claude Code)
https://github.com/woocommerce/woocommerce/pull/66211
- **Objections (ralucaStan):**
  - "The id doesn't guarantee the same price, right? a Flat Rate method with cost that varies by postcode, or 'Free shipping over $X for specific postcodes', stays in the same zone but yields a different rate".
  - "There is no checkout to return to; the pay for order flow doesn't have a cart object".
  - A first-time-address edge case also had to be patched.
- **Outcome:** closed for a more robust human solution.
- **Patterns:** an identity check used as a proxy for value equality (same method id ≠ same price); wrong model of the flow (assumed a cart exists).

### 19. woocommerce/woocommerce#68575: Record reporting currency and conversion basis in Analytics order stats (AI workflow)
https://github.com/woocommerce/woocommerce/pull/68575
- **What the agent did:** five new DB columns, a migration, and a basis enum carried through every report.
- **The author's own retraction:** "my AI workflow aggressively tried to force a solution regarding stores accepting multi currency payments... any store that uses a non-standard way to accept multi currency payments would result in Analytics showing 'unavailable'. I don't think this is the correct approach."
- **Patterns:** over-engineering; the "safe" default (legacy and unknown rows treated as unknown) degraded every existing store.

### 20. symfony/symfony#66279: [AssetMapper] Escape single quotes in data: URL loaders (Claude Code)
https://github.com/symfony/symfony/pull/66279
- **Objection:** nicolas-grekas, closing in favour of #66282, which "also covers paths containing `'`, `\` or `#`: here, es-module-shims eats the backslash added by `addslashes()`, so e.g. `it's.css` still breaks."
- **Pattern:** escaped only the reported character; the escaping does not survive the next consumer, so the input still breaks.

### 21. ocaml/ocaml#14369: DWARF v5 debugging support, ~13k lines (Claude Code + Codex, Nov 2025)
https://github.com/ocaml/ocaml/pull/14369
- **Objections:**
  - gasche: "We would appreciate people discussing design before they dump 13K-lines PRs", and "hard to review, and very lightly tested... we will have to pay the cost of fixing them".
  - Jeremy Yallop: "Why did the files that you submitted name Mark Shinwell as the author?" The code had copied or attributed provenance.
  - Closed and locked.
- **Patterns:** oversized, no design agreement, light tests, provenance problems.

### 22. laravel/framework#58925: per-connection migration path config (Claude Code)
https://github.com/laravel/framework/pull/58925
- **Objection:** Taylor's standard close: "we need to be very careful regarding the amount of code we include... consider releasing your code as a package." The author then added a second feature in the same PR.
- **Pattern:** scope creep and feature bloat.

### 23. nextcloud/server#60348 and #61827: closed under the Nextcloud AI policy
https://github.com/nextcloud/server/pull/60348
https://github.com/nextcloud/server/pull/61827
- **Objection:** provokateurin: "Your PR description is AI-written, so I'm closing it as per our AI policy". The policy (https://github.com/nextcloud/.github/blob/master/AI_POLICY.md) also says: "If a reviewer asks 'why does this work this way?' and the answer is 'the AI wrote it,' the PR will be closed."
- In #60348 the reviewer also flagged a dead parameter: "$throwOnData is always true, I would remove the parameter, it's confusing for no reason."
- **Pattern:** process and provenance (not reproducible in a benchmark); plus dead or useless parameters.

### Project policies (context, not PRs)
- **QEMU** (https://www.qemu.org/docs/master/devel/code-provenance.html) "declines contributions believed to include or derive from AI-generated content". Reasons: copyright, training-data licences, DCO.
- **Ghostty** policy (known from search summaries only; I did not open the file):
  - AI use must be disclosed;
  - the contributor must "fully understand all code";
  - since Jan 2026, AI PRs are only accepted for pre-approved issues;
  - slop authors are put on a public denounce list.
- **Gentoo, NetBSD, curl:** not individually verified in this session, so no URLs are cited.
- **Base-rate data:** arXiv 2602.04226, "Why Agentic-PRs Get Rejected" (https://arxiv.org/html/2602.04226v1), studied 932k agentic PRs.
  - 69% of rejections had no feedback at all.
  - Of the explained rejections: alternative solution 9.2%, inactive 7.5%, experimentation 2.9%, wrong location 1.8%, too large 1.5%, bug/API break 1.1%, no added value 1.1%, non-optimal design 0.9%.

---

## B. Failure patterns ranked by frequency (in the 23 PRs above)

| # | Pattern | Count | PRs |
|---|---|---|---|
| 1 | Did not understand an invariant, contract or domain rule (opaque values, cache invalidation, races, canonical decision bypassed, identity ≠ value, flow model wrong) | 9 | 115762, 115826, 125650, 124257, 121038, 66653, 66211, 68575, 115733 |
| 2 | Kept guessing or claimed "fixed" after CI failed; regression whack-a-mole | 8 | 115733, 115743, 115762, 115826, 115829, 121038, 68691, 123397 |
| 3 | Symptom fix, special case or wrong layer (guard clause, lexer special case, post-processing output text, wrong compiler phase, "turned an if into a helper") | 7 | 115733, 115732, 62036, 123397, 131794, 121038, 66653 |
| 4 | Test malpractice (deleted, narrowed or edited tests to pass; tests not run; tested the wrong unit; test pins the bug; flaky time tests; non-default params; leaked state) | 7 | 115733, 121038, 63285, 62036, 68691, 66653, 123397 |
| 5 | Incomplete coverage: fixed only the cited examples, missed call sites, case variants or the next special char | 6 | 115927, 116257, 66279, 121038 ("..."), 124257 (case), 66211 (first-time address) |
| 6 | Over-engineering, scope creep or no proven value | 6 | 14369, 68575, 58925, 125650, 131794, 123397 |
| 7 | Ignored repo conventions or abstractions (style, test file placement, reflection hacks, unrelated file changes, dead params) | 6 | 115743, 115826, 62036, 68691, 123397, 60348 |
| 8 | Broke an unrelated platform, public behaviour or performance | 6 | 115762, 121038 (OriginalRootDirectory), 124257, 131794, 68575, 115743 |
| 9 | Hallucinated or invented API or semantics | 3 | 115762 (version scheme, undefined macros), 121038 (`PathInternal.IsExtended`), 62036 (attribute never used) |
| 10 | Process or provenance (AI description, copied attribution) | 3 | 14369, 60348, 61827 (not benchmarkable) |

---

## C. PHP 7.4 business-logic benchmark traps (hidden-test-checkable)

Each trap gives the visible task, what the hidden tests check, and why an agent with the pattern fails.

### 1. Invariant or contract not understood → "Taxable address with override hook"
- **Task:** `TaxAddressResolver::resolve(Customer $c, Cart $cart): Address`. Use the shipping address unless the cart "does not need shipping", then use billing.
- **Hidden context:** `ShippingPolicy::needsShipping(Cart)` already exists and honours a registered override callback (`ShippingPolicy::addOverride(callable)`), like WooCommerce's filter.
- **Hidden tests:**
  1. A virtual cart with an override forcing shipping must use the shipping address.
  2. `resolve()` called for customer B while the "current session" cart belongs to A must use the passed `$cart`, not a static or global.
- **Why agents fail:** they re-scan `$cart->items()` for `isVirtual()`, which reproduces WC#66653.
- **Second variant (cache):** `RateProvider` with a memoised `current()` plus `clear()` and `remove($key)` methods. Hidden test: after `remove('default')`, `current()` must reload (reproduces dotnet#125650).

### 2. Guessing after CI failure → "multi-stage pricing pipeline with cross-coupled tests"
- **Task:** fix rounding in `InvoiceCalculator`.
- **Trap:** the visible failing test can be made green by changing the rounding at line-item level. Hidden tests check invoice-level totals, so those break: the rule is round per line before tax, not after.
- **Grading:** grade against the full hidden suite. Only a fix to the rounding *order* passes both.
- **Also grade:** that no visible test file changed (diff check).

### 3. Symptom fix or special case → "Discount expression parser"
- **Task:** "`price NOT> 100` throws." Note that `qty NOT> 5` already works, mirroring dotnet#115732.
- **Hidden tests:** the operator fails for *any* boolean or float left operand and for `NOT<`, `NOT=` too.
- **Why agents fail:** special-casing the token string or wrapping in try/catch returning false fails the hidden cases.
- **Variant:** an array index out of range in `allocateStock()`. The guard `if (!isset($stack[$i])) return 0;` passes the visible test. The hidden test expects a correct non-zero allocation, because the real bug is an off-by-one in the backtracking loop (mirrors dotnet#115733).

### 4. Test malpractice → "Coupon expiry timezone bug"
- **Task:** `Coupon::isExpired()` uses `new DateTime()` (server timezone). Fix it to the store timezone.
- **Hidden checks:**
  - an injected clock with a DST-transition date (Europe/Istanbul or America/New_York);
  - visible test files are byte-identical (no deleting or editing tests);
  - the agent's added test, if any, must fail on the original code.
- **Why agents fail:** they replace `DateTime()` with `time()` arithmetic and assume 24h days, which fails the DST test (mirrors aspnetcore#63285).

### 5. Incomplete coverage → "Status codes not propagated" or "rename across layers"
- **Task:** "`PaymentException` loses the gateway error code in `charge()`, `refund()` and `capture()`."
- **Hidden context:** `void()` and `authorize()` have the same bug.
- **Hidden tests:** check all five.
- **Why agents fail:** they patch only the 3 methods listed in the issue (mirrors dotnet#116257).
- **Variant:** escape CSV export fields for `'`. Hidden tests use `"`, `\`, `,`, a newline and a leading `=` (formula injection). `addslashes()` fails them (mirrors symfony#66279).

### 6. Over-engineering → "Add currency to order report"
- **Task:** "Show order totals in store currency; orders already store `currency` and `exchange_rate`."
- **Hidden tests:**
  - legacy orders with a null `exchange_rate` and the store currency must still sum (not become "unavailable" or null);
  - the public method signatures of `ReportService` stay unchanged;
  - no schema or array-shape changes for existing callers.
- **Why agents fail:** adding new required fields or a strict "unknown basis → exclude" rule fails (mirrors WC#68575).

### 7. Ignored repo conventions or abstractions → "Use existing Money/Clock/Repository"
- **Context:** the repo has `Money` (integer minor units, `Money::add()`), `ClockInterface`, and `OrderRepository::findByCustomer()`.
- **Task:** "add loyalty points accrual".
- **Hidden tests:**
  - inject a FrozenClock (fails if the code calls `time()` or `date()`);
  - pass a `Money` with 3-decimal currency (KWD), which fails float math;
  - use a repository spy expecting no direct PDO.
- **Why it works as a trap:** reflection or `static::` hacks to reach private state fail with a decorated subclass in the hidden test (mirrors aspnetcore#62036).

### 8. Broke another platform, public behaviour or perf → "Normalise SKU"
- **Task:** "trim trailing spaces from SKUs on save".
- **Hidden tests:**
  - `Product::getOriginalSku()`, a public accessor, must still return the raw input (mirrors OriginalRootDirectory in dotnet#121038);
  - SKUs ending with `.` or `...` keep their dots;
  - multibyte SKUs (`"ÜRÜN "`) work, which fails if the agent uses non-mb functions on a Turkish-locale input;
  - a performance-shaped check: `normalize()` is called 100k times without building per-call arrays or regexes, under a time budget.

### 9. Hallucinated API or semantics → "Opaque version or ID"
- **Task:** "`TaxTable::version()` must change when the rates for a *region* change."
- **Trap:** the version is an opaque hash per region. Hidden tests:
  - two regions return different versions;
  - a future date not in any lookup table still works (mirrors dotnet#115762's hard-coded iOS map).
- **PHP 7.4 specifics:** hidden tests run strictly on PHP 7.4, so PHP 8-only functions (`str_contains`, `str_starts_with`, `array_is_list`, `match`, nullsafe `?->`, constructor promotion) are fatal or parse errors. This is a cheap, deterministic "hallucinated API" detector.

### 10. Identity used as a value proxy → "Pay-for-order shipping recheck"
- **Task:** when a customer changes the address on a pending order, keep the shipping cost unless the shipping method changes.
- **Hidden tests:**
  - the same method id with postcode-dependent flat rates must give a recalculated price;
  - an order with no recorded destination accepts the first address.
- **Why agents fail:** comparing `method_id` only fails (mirrors WC#66211).
