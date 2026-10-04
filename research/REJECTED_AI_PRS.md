# 1000 rejected AI-agent pull requests

Source: AIDev dataset (hao-li/AIDev on Hugging Face, curated split: 71,677 agent PRs in repositories with >100 stars, updated 2026-08-31). Of these, 17,294 were closed without merge. 4,764 had at least one comment or review of 40+ characters from someone other than the PR author. 1,000 were selected: all 36 PHP PRs, plus the 964 non-PHP PRs whose feedback had the most technical vocabulary and was not about docs, CI or dependencies. Each PR was classified by reading its feedback, truncated to 1,400 characters (10 classifier agents, 100 PRs each).

Full table: `rejected_ai_prs_1000.csv` (idx, usable, reason, domain, language, agent, repo, url, title, why, php_trap).

## Usability for the benchmark

| Tier | Meaning | Count |
| --- | --- | --- |
| A | Concrete technical failure; portable to a PHP business-logic task with a hidden-test-checkable outcome | 22 |
| B | Real agent failure pattern, but needs heavy adaptation or is not test-checkable (style, review process, UI, infra, language-specific) | 349 |
| C | Not usable: process (superseded, duplicate, not wanted), no technical reason, or bot-only feedback | 629 |


Why C dominates: about 150 "human" threads are automated reviews posted from user accounts (crewAI "crew of AI Agents", Bun robobun CI listings, CLA/template bots, Claude auto-fix requests). Codex, Cursor and Claude Code PRs were mostly closed without any explanation.


## Rejection reasons (all 1,000 / A+B only)

| Reason | All | A+B |
| --- | --- | --- |
| abandoned-no-reason | 211 | 0 |
| conventions-design | 162 | 89 |
| ci-failing | 158 | 33 |
| other | 116 | 1 |
| incorrect-fix | 114 | 99 |
| incomplete-edge-cases | 70 | 60 |
| misunderstood-task | 49 | 39 |
| scope-overengineering | 36 | 15 |
| maintainer-not-wanted | 20 | 1 |
| environment-tooling | 17 | 1 |
| superseded-duplicate | 12 | 0 |
| breaking-change | 10 | 10 |
| performance | 9 | 8 |
| test-tampering | 7 | 7 |
| hallucinated-api | 6 | 5 |
| security | 3 | 3 |

## By agent

| Agent | A | B | C |
| --- | --- | --- | --- |
| Copilot | 21 | 300 | 256 |
| Devin | 1 | 29 | 239 |
| OpenAI_Codex | 0 | 12 | 100 |
| Claude_Code | 0 | 4 | 18 |
| Cursor | 0 | 2 | 15 |
| Google_Jules | 0 | 2 | 1 |

## All A-tier PRs (directly portable)

| # | Lang | PR | Reason | What the maintainer objected to | PHP trap |
| --- | --- | --- | --- | --- | --- |
| 15 | PHP | [phikiphp/phiki](https://github.com/phikiphp/phiki/pull/76) | incomplete-edge-cases | Recursion guard wrong: 'a pattern that matches but never advances' should be skipped, move to next pattern; duplicated code | Tokenizer with zero-width regex match must not loop forever: hidden test feeds rules including empty-match pattern, expects tokens produced and termination. |
| 106 | Python | [microsoft/autogen](https://github.com/microsoft/autogen/pull/6565) | incorrect-fix | Reviewer: 'It will always go to C. We need to associate a condition with this'; remove str option; later test errors from pydantic underscore field. | Graph with conditional edges from one node: callable conditions per edge; input message -> hidden test asserts only matching branch is chosen, not always the fallback. |
| 177 | Ruby | [benwbrum/fromthepage](https://github.com/benwbrum/fromthepage/pull/4810) | incorrect-fix | Failing test: 'Deed.order_by_recent_activity lists deeds in order by most recently created' returned wrong deed after adding WORK_ADDED to collection deeds; maintainer unsure of desired behavior. | Activity feed filter: include an extra event type; input mixed-type events -> hidden test asserts most-recent-first ordering and filter set unchanged for other types. |
| 266 | Go | [Azure/azure-service-operator](https://github.com/Azure/azure-service-operator/pull/4864) | incorrect-fix | Merge logic: 'If other sets false explicitly, base is true, we just don't merge? That should be an error'; redundant len checks; merge granularity should be group level | Merge two config arrays/maps where override sets explicit false/0/'' over true base -> hidden test checks explicit falsy values override or raise conflict, slices concatenate |
| 271 | TypeScript | [microsoft/vscode](https://github.com/microsoft/vscode/pull/250880) | incorrect-fix | Token typing wrong: 'git s/' should be Argument; second command after && not parsed as command; suggestions shown right after ';' without space | Tokenize shell-like string with &&, ;, / separators -> classify each token as command or argument; hidden test checks cursor after separator without space yields none |
| 373 | Python | [mabel-dev/opteryx](https://github.com/mabel-dev/opteryx/pull/2860) | incorrect-fix | Pushing LIMIT/HeapSort before projection caused many regression test failures; sort is pushable only when sort field is not created by the projection. | Planner pushes LIMIT/sort before projection: ORDER BY a projected computed alias must still sort correctly; hidden tests check sort on source column (pushdown ok) vs computed column (no pushdown). |
| 450 | Rust | [tomhrr/cosh](https://github.com/tomhrr/cosh/pull/173) | incomplete-edge-cases | '>>' append redirect via '>>:' marker would break if target filename itself starts with '>>:'; new test fails. | Redirect parser with in-band '>>' prefix: filename starting with '>>' or '>>>>file' -> hidden test expects correct append flag and filename. |
| 470 | Python | [adamchainz/django-upgrade](https://github.com/adamchainz/django-upgrade/pull/598) | incomplete-edge-cases | Fixer must guard against calls with more positional args than known and keyword-only args already present; 'cannot assume code is correct'. | Call-rewriting function converting positional args to keywords: input with extra positionals or existing keyword -> hidden test expects unchanged output. |
| 501 | Rust | [oxc-project/oxc](https://github.com/oxc-project/oxc/pull/12599) | incorrect-fix | Pattern-based unification would allow invalid Jest chaining patterns; maintainer: 'we can't allow any invalid Jest chaining pattern'. | Validator for call chains (e.g. test.only.each) generalized by pattern; hidden test feeds invalid chains that must be rejected. |
| 541 | Swift | [openhab/openhab-ios](https://github.com/openhab/openhab-ios/pull/954) | incomplete-edge-cases | Validation does not check rect/pattern dimensions exceed 4000/5000; only checks existence of rect or attribute equal to a specific value. | SVG-like input with width/height over threshold in rect/pattern -> hidden test expects rejection; naive check only tests exact values/presence. |
| 555 | C# | [dotnet/msbuild](https://github.com/dotnet/msbuild/pull/12591) | breaking-change | Change breaks contract: method returned false only when args unparseable, now also false for invalid version strings. | Function with 'handled/unhandled' return contract; hidden test with invalid version string expects old behavior (exception/true path) preserved. |
| 582 | Java | [camunda/camunda](https://github.com/camunda/camunda/pull/37452) | incomplete-edge-cases | Only captures first correlation per messageKey; 'capture all correlations to subscriptions' without overriding each other; needs proper DB key. | Event list with multiple correlations per message key -> hidden test expects all stored under composite key; naive map keyed by messageKey overwrites. |
| 665 | Scala | [NVIDIA/spark-rapids](https://github.com/NVIDIA/spark-rapids/pull/13234) | incorrect-fix | revans2: on CPU, writing noop format in ignore mode errors (AnalysisException); GPU rule must match that behavior. | Writer accepting save mode 'ignore' for a sink that must reject it -> hidden test expects exception for ignore mode, accepts append/overwrite. |
| 723 | Rust | [tomhrr/cosh](https://github.com/tomhrr/cosh/pull/181) | incorrect-fix | Tests unrelated to ROV; prefix filter 'finds all VRPs with prefix equal or larger than announced'; cache clearing line does nothing. | Route origin validation by ASN then prefix: announced prefix vs VRP covering prefix with maxLength -> hidden test checks valid/invalid/not-found and cache reset. |
| 736 | Ruby | [Homebrew/brew](https://github.com/Homebrew/brew/pull/20658) | breaking-change | Questioned backwards compatibility: default with XDG unset must stay same and existing Brewfile location must not be missed; wrong env var assumption. | Config path resolution with env vars: XDG unset -> legacy path; existing legacy file must still be found; XDG set and no file -> new path. Hidden tests per combination. |
| 793 | Ruby | [benwbrum/fromthepage](https://github.com/benwbrum/fromthepage/pull/4783) | incomplete-edge-cases | Regex change to three chars of any class lets punctuation match, so 'Mr.'/'Dr.' still match; spec re-implements logic rather than calling possible_duplicates. | Duplicate-subject detection: filter tokens like 'Mr.', 'Dr.', single initials; hidden test calls real function with punctuated titles and expects no false positives. |
| 864 | Java | [ArcadeData/arcadedb](https://github.com/ArcadeData/arcadedb/pull/2587) | incorrect-fix | SQL index with ORDER BY still returns duplicate entries; maintainer pushed failing test testDuplicateEntries, 'original issue is still here'. | Query rows via index plus ORDER BY where multi-key index yields same record twice -> hidden test expects each record exactly once in sorted order. |
| 871 | Python | [opsmill/infrahub](https://github.com/opsmill/infrahub/pull/6767) | breaking-change | Python-keyword name check on relationships is breaking since it worked before; should apply only in strict mode; fix belongs in schema_branch.py. | Validate schema names: attributes with reserved words always error, relationships only error when strict=true -> hidden test checks non-strict relationship with keyword name still passes. |
| 890 | TypeScript | [microsoft/vscode](https://github.com/microsoft/vscode/pull/268211) | incorrect-fix | Wrong list logic: if resourceMetadataChallenge exists it must be the only candidate URL, otherwise add the two well-known ones; also duplication. | Build candidate discovery URL list: challenge present -> exactly [challenge]; absent -> two well-known paths in order -> hidden test asserts exact lists. |
| 901 | C# | [dotnet/aspnetcore](https://github.com/dotnet/aspnetcore/pull/62623) | incorrect-fix | Object-level IValidatableObject validation must be skipped/behave like System.ComponentModel Validator when property validation fails; maintainer disputed semantics, wanted matching TryValidateObject behavior. | Entity with field rules plus object-level validate(): input with invalid field -> hidden test asserts object-level validator is not invoked (and invoked when fields valid). |
| 962 | Ruby | [benwbrum/fromthepage](https://github.com/benwbrum/fromthepage/pull/4811) | misunderstood-task | 'Completely misunderstands the issue': verbatim exports must preserve hyphen continuation marker, word-wrapped exports should replace sigil and join the word. | Text with hyphen line-continuation markers: verbatim export keeps marker, wrapped export removes it and joins word halves -> hidden tests per mode. |
| 981 | Python | [marimo-team/marimo](https://github.com/marimo-team/marimo/pull/3806) | incomplete-edge-cases | Semicolon output suppression parsing: needs handling of comments, multiple statements, assignments, '#' inside strings; expression must be const None in ';' cases. | Parser for trailing ';' suppression: inputs with comments, '#' in strings, '1;2;3;' -> hidden test checks suppressed flag per case. |

## PHP PRs (all 36)

| # | Tier | PR | Reason | Feedback |
| --- | --- | --- | --- | --- |
| 1 | B | [humanmade/S3-Uploads](https://github.com/humanmade/S3-Uploads/pull/721) | misunderstood-task | Removed dependency instead of making matrix work: 'did you forget the original task?', wanted newer PHP supported |
| 2 | C | [PrestaShop/PrestaShop](https://github.com/PrestaShop/PrestaShop/pull/39688) | other | Only bot complaint about PR template fields (branch, type, category); no technical feedback |
| 3 | C | [elabftw/elabftw](https://github.com/elabftw/elabftw/pull/5894) | other | Only CLA-assistant bot comment; no technical feedback |
| 4 | B | [mglaman/phpstan-drupal](https://github.com/mglaman/phpstan-drupal/pull/910) | conventions-design | Asked to extend IntegerRangeType instead of overriding describe, then fix lint and test failures; PHPStan type-specific |
| 5 | C | [nextcloud/news](https://github.com/nextcloud/news/pull/3282) | maintainer-not-wanted | 'I think this is a bad idea', packaged app has dependencies; should be handled by server |
| 6 | B | [orangehrm/orangehrm](https://github.com/orangehrm/orangehrm/pull/1908) | ci-failing | Workflow checks failing repeatedly; told not to change things blindly, read job logs |
| 7 | C | [RickDBCN/filament-email](https://github.com/RickDBCN/filament-email/pull/108) | abandoned-no-reason | Backport to 2.x requested; no review feedback, closed without stated reason |
| 8 | B | [WP-Autoplugin/wp-autoplugin](https://github.com/WP-Autoplugin/wp-autoplugin/pull/24) | security | shell_exec() not available on many hosts and flagged by code checkers; new classes not integrated into workflow |
| 9 | C | [magicbug/Cloudlog](https://github.com/magicbug/Cloudlog/pull/3340) | abandoned-no-reason | WIP PR, maintainer only asks whether agent can do the task |
| 10 | B | [magicbug/Cloudlog](https://github.com/magicbug/Cloudlog/pull/3335) | incomplete-edge-cases | PHP 8.4 compat: 'what about other files', only 6 files changed in whole codebase, legacy code needs checking |
| 11 | C | [redaxo/redaxo](https://github.com/redaxo/redaxo/pull/6329) | maintainer-not-wanted | Unsure auto-generated instructions file is wise; wants specific CMS details; maintainer takes over |
| 12 | C | [ChurchCRM/CRM](https://github.com/ChurchCRM/CRM/pull/7421) | other | Feedback is just PR description summary; no stated rejection reason |
| 13 | B | [shopware/shopware](https://github.com/shopware/shopware/pull/8269) | conventions-design | Request nullability wrong, wanted deprecation tags, feature flag and GDPR/cookie concerns; JS plugin approach questioned |
| 14 | B | [w7corp/easywechat](https://github.com/w7corp/easywechat/pull/2951) | misunderstood-task | 'Don't copy 5.x docs'; read 4.x code first then compare docs |
| 15 | A | [phikiphp/phiki](https://github.com/phikiphp/phiki/pull/76) | incomplete-edge-cases | Recursion guard wrong: 'a pattern that matches but never advances' should be skipped, move to next pattern; duplicated code |
| 16 | B | [knuckleswtf/scribe](https://github.com/knuckleswtf/scribe/pull/1030) | incorrect-fix | 'This is a bug from Laravel, not us. This doesn't fix it.' |
| 17 | C | [mautic/mautic](https://github.com/mautic/mautic/pull/15250) | superseded-duplicate | 'Stop creating more PRs'; push to existing PR 15249 |
| 18 | C | [WordPress/abilities-api](https://github.com/WordPress/abilities-api/pull/65) | maintainer-not-wanted | Maintainers question value of instructions file versus linking existing docs; asks for evals |
| 19 | C | [elabftw/elabftw](https://github.com/elabftw/elabftw/pull/5892) | other | Only CLA bot and a user's non-maintainer question on revision semantics; no stated technical reason |
| 20 | C | [Automattic/wordpress-mcp](https://github.com/Automattic/wordpress-mcp/pull/30) | superseded-duplicate | Feature added in another PR #31 |
| 21 | B | [lakasir/lakasir](https://github.com/lakasir/lakasir/pull/328) | incorrect-fix | Used is_deleted flag instead of soft delete; creating product with barcode fails with 'additional barcode has already been taken' validation |
| 22 | C | [elabftw/elabftw](https://github.com/elabftw/elabftw/pull/5893) | other | Only CLA bot comment |
| 23 | C | [shopware/shopware](https://github.com/shopware/shopware/pull/11090) | other | Only CLA bot comment |
| 24 | B | [pimcore/pimcore](https://github.com/pimcore/pimcore/pull/18585) | conventions-design | Modified composer.json against copilot-instructions.md rules |
| 25 | C | [nextcloud/server](https://github.com/nextcloud/server/pull/54525) | other | WIP/joke reactions; no technical feedback |
| 26 | C | [mautic/mautic](https://github.com/mautic/mautic/pull/15578) | superseded-duplicate | Maintainers already have a fix; concern about duplicated language strings |
| 27 | B | [cakephp/cakephp](https://github.com/cakephp/cakephp/pull/18901) | ci-failing | Rector still reports 60+ issues; agent introduced syntax errors in multiple files; 'just close the pull request' |
| 28 | C | [symfony/symfony](https://github.com/symfony/symfony/pull/61463) | other | Bot rejects Draft PRs; no technical feedback |
| 29 | C | [WordPress/abilities-api](https://github.com/WordPress/abilities-api/pull/63) | maintainer-not-wanted | Closed: parts 'don't make too much sense', maintainers pursuing own design for filtering |
| 30 | B | [pimcore/pimcore](https://github.com/pimcore/pimcore/pull/18590) | incomplete-edge-cases | 'Have a look at getDataEditmode()' - fix incomplete for editmode data |
| 31 | B | [CMB2/CMB2](https://github.com/CMB2/CMB2/pull/1545) | misunderstood-task | Unclear difference from phpunit.xml.dist; did tests run/pass?; PR meant to fix #1544 |
| 32 | B | [php-llm/llm-chain](https://github.com/php-llm/llm-chain/pull/344) | conventions-design | Rework to getId() returning Symfony Uuid; use Uid component not self-developed hash; run make cs/ci |
| 33 | B | [wp-media/wp-rocket](https://github.com/wp-media/wp-rocket/pull/7762) | incomplete-edge-cases | Beacon suggest belongs in rocket_insights_section() and Render method; missing changes in Page.php, tests |
| 34 | B | [PopupMaker/Popup-Maker](https://github.com/PopupMaker/Popup-Maker/pull/1097) | incorrect-fix | settings.id undefined, throws error on every close; popup nesting behavior odd |
| 35 | C | [shopware/shopware](https://github.com/shopware/shopware/pull/12591) | other | WIP 'created by mistake', CLA bot only |
| 36 | C | [mautic/mautic](https://github.com/mautic/mautic/pull/15251) | superseded-duplicate | 'Stop creating more PRs'; push to PR 15249 |

## B-tier examples by reason (patterns worth adapting)


### conventions-design

- [mglaman/phpstan-drupal](https://github.com/mglaman/phpstan-drupal/pull/910) (PHP): Asked to extend IntegerRangeType instead of overriding describe, then fix lint and test failures; PHPStan type-specific
- [shopware/shopware](https://github.com/shopware/shopware/pull/8269) (PHP): Request nullability wrong, wanted deprecation tags, feature flag and GDPR/cookie concerns; JS plugin approach questioned
- [pimcore/pimcore](https://github.com/pimcore/pimcore/pull/18585) (PHP): Modified composer.json against copilot-instructions.md rules
- [php-llm/llm-chain](https://github.com/php-llm/llm-chain/pull/344) (PHP): Rework to getId() returning Symfony Uuid; use Uid component not self-developed hash; run make cs/ci
- [NewFuture/DDNS](https://github.com/NewFuture/DDNS/pull/524) (Python): Maintainer asked to simplify config-path splitting, drop list support, remove needless helper functions, generalize _flatten_single_config.

### incorrect-fix

- [knuckleswtf/scribe](https://github.com/knuckleswtf/scribe/pull/1030) (PHP): 'This is a bug from Laravel, not us. This doesn't fix it.'
- [lakasir/lakasir](https://github.com/lakasir/lakasir/pull/328) (PHP): Used is_deleted flag instead of soft delete; creating product with barcode fails with 'additional barcode has already been taken' validation
- [codeceptjs/CodeceptJS](https://github.com/codeceptjs/CodeceptJS/pull/5124) (JavaScript): Cypress acceptance tests fail; 'use proper cypress APIs not simulation'
- [Azure/azure-sdk-for-python](https://github.com/Azure/azure-sdk-for-python/pull/42027) (Python): Reviewer: change 'causes a regression in behavior for evaluators such as GroundednessProEvaluator'; recording mismatch of context payload; also wanted PM sign-off, changelog.
- [dotnet/runtime](https://github.com/dotnet/runtime/pull/115826) (C#): Reviewer: claim 'not true, handlers only nulled in Dispose'; nulling handlers unsafe while active entry in use; wanted Interlocked CAS Disposed/Expired states.

### incomplete-edge-cases

- [magicbug/Cloudlog](https://github.com/magicbug/Cloudlog/pull/3335) (PHP): PHP 8.4 compat: 'what about other files', only 6 files changed in whole codebase, legacy code needs checking
- [pimcore/pimcore](https://github.com/pimcore/pimcore/pull/18590) (PHP): 'Have a look at getDataEditmode()' - fix incomplete for editmode data
- [wp-media/wp-rocket](https://github.com/wp-media/wp-rocket/pull/7762) (PHP): Beacon suggest belongs in rocket_insights_section() and Render method; missing changes in Page.php, tests
- [thomhurst/ModularPipelines](https://github.com/thomhurst/ModularPipelines/pull/1192) (C#): Maintainer: after Task.WhenAny succeeds, cancel the other tasks so they are not left hanging; plus pipeline module failure.
- [microsoft/autogen](https://github.com/microsoft/autogen/pull/6877) (Python): Maintainer wanted docstring example, unit tests with replay model client, structured output with JSON-mode fallback and tests; missing items.

### misunderstood-task

- [crewAIInc/crewAI](https://github.com/crewAIInc/crewAI/pull/2777) (Python): Reviewer: run_id creates short-term memory only, no long-term memory; should call memory.add() twice; no way to turn off agent memories.
- [zapier/zapier-platform](https://github.com/zapier/zapier-platform/pull/1118) (JavaScript): Agent made type field optional; maintainer wanted required (breaking, next major); tests and smoke tests failing
- [Azure/azure-sdk-for-python](https://github.com/Azure/azure-sdk-for-python/pull/42190) (Python): Reviewers corrected exact error message wording and flag format (--ClaimsChallenge) and asserted strings in tests.
- [microsoft/typescript-go](https://github.com/microsoft/typescript-go/pull/1445) (Go): Told to reset branch and write failing test first; was papering over instead of fixing.
- [dotnet/aspnetcore](https://github.com/dotnet/aspnetcore/pull/62000) (C#): Modified MVC validation instead of src/Http; tests should validate PropertyNamePolicy respected.

### ci-failing

- [thomhurst/TUnit](https://github.com/thomhurst/TUnit/pull/2912) (C#): Rename ShouldSkipHook, PR hangs, AsyncLocal/ExecutionContext values must flow from hooks to tests; repeated build failures
- [javalin/javalin](https://github.com/javalin/javalin/pull/2457) (Kotlin): Tests failing (Failures: 1, Errors: 9) after removing Jetty resource handler; asked to keep fixing.
- [dotnet/msbuild](https://github.com/dotnet/msbuild/pull/11953) (C#): Build fails with CS0618 after deprecating ThreadId; replacing usages with 0.
- [streamich/memfs](https://github.com/streamich/memfs/pull/1113) (TypeScript): Typecheck failing and tests not passing; asked to add Dir tests.
- [open-telemetry/opentelemetry-java-instrumentation](https://github.com/open-telemetry/opentelemetry-java-instrumentation/pull/14874) (Java): Tests failing in CI due to different metric descriptions; descriptions must equal semantic convention 'brief'.

### scope-overengineering

- [dotnet/efcore](https://github.com/dotnet/efcore/pull/36668) (C#): Remove code handling manual SYSTEM_VERSIONING changes; change test to non-constant computed column and fix resulting SQL error.
- [javalin/javalin](https://github.com/javalin/javalin/pull/2443) (Kotlin): PR should target migration branch or bring Jetty 12 changes; remaining ResourceHandler issues; upgradeSessionAttrsKey still used.
- [slatedb/slatedb](https://github.com/slatedb/slatedb/pull/887) (Rust): Too much configurability; just use single object_store_retry_duration Duration with ExponentialBuilder total delay; remove with_duration.

### performance

- [microsoft/wassette](https://github.com/microsoft/wassette/pull/69) (Rust): Suggested parallelizing file loads rather than serial; later rebase, failing tests and fmt diffs.
- [commonwarexyz/monorepo](https://github.com/commonwarexyz/monorepo/pull/1863) (Rust): 'We must have a more efficient lookup mechanism than replaying all entries in a section'; edge case at oldest section; test config.
- [microsoft/fluentui-blazor](https://github.com/microsoft/fluentui-blazor/pull/3857) (C#): Requires defining defaults for all properties of all components, hurting performance; name must match property; cannot share default across components.
- [promptfoo/promptfoo](https://github.com/promptfoo/promptfoo/pull/5168) (TypeScript): json_extract subqueries will explode query time on large datasets; use UUIDs; move methods to model.

### breaking-change

- [dotnet/runtime](https://github.com/dotnet/runtime/pull/121038) (C#): Changes behavior of FileSystemEntry.OriginalRootDirectory; fix should be at enumerable consumers level
- [open-telemetry/opentelemetry-dotnet](https://github.com/open-telemetry/opentelemetry-dotnet/pull/6500) (C#): Don't introduce new public API OtlpExporterOptions.UserAgent; read from OTEL_EXPORTER_OTLP_TRACES_HEADERS env var instead; also null-deref build error
- [dotnet/runtime](https://github.com/dotnet/runtime/pull/115927) (C#): Rename Perf to Performance is documented breaking switch; needs breaking-change process; missed DOTNET_WasmPerfInstrumentation occurrences; naming disputed
- [dotnet/aspnetcore](https://github.com/dotnet/aspnetcore/pull/62539) (C#): 'We don't want to add new exception type, revert public API'; use existing TimeoutException; removed cancelled-task test case; custom CancellationToken not handled.
- [bespokelabsai/curator](https://github.com/bespokelabsai/curator/pull/305) (Python): Replacing the pickler breaks other logic supported by huggingface pickler; root cause not understood.
