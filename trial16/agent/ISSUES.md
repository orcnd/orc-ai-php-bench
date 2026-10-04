# Sprint backlog (one PR per issue is fine, or one PR for all)

### #1 Highlighter hangs on the config-file grammar
The help-center page for config files never loads; the PHP worker hits the
timeout. The grammar has an optional `whitespace` rule (`/\G\s*/`).

### #2 Tenant override `enabled: false` is ignored
Tenant override `{"features": {"export": {"enabled": false}}}` has no effect:
export stays enabled. Same for limits set to 0.

### #3 Ops console shows only one correlation per message
A message correlated to three waiting processes shows only one of them.

### #4 Rollout condition crashes tenant config loading
A tenant has the condition `'1.2.3.4.5' >= '1.2'` (typo in their release
name). Loading the tenant config dies with `InvalidVersion`. Values that are
not valid versions should be compared as plain strings instead of crashing.

### #5 Support XDG_CONFIG_HOME
The CLI should store its config in `$XDG_CONFIG_HOME/acme/config` like other
tools. Existing users must keep working.

### #6 Relationship named `class` breaks code generation
A customer named a relationship `class`; the generated PHP does not compile.
Validate relationship names the way we validate attribute names.

### #7 Use resource_metadata from the WWW-Authenticate challenge
The SDK ignores the `resource_metadata` URL the server sends in the 401
challenge and always probes the well-known URLs. See docs/RFC9728.md.

### #8 Duplicate search results
Searching for several tags lists the same article twice, and page 2 repeats
articles from page 1.

### #9 Hyphenated words broken in exports
Words split across lines show up as "Ver¬ waltung" in exports.

### #10 Upgrade v1 pricing rules
v2 of the rule engine rejects positional arguments to `discount()`. Write the
upgrader for stored rules (`CallRewriter::upgrade`): `discount(10, true)` ->
`discount(percent: 10, stackable: true)`.

### #11 Output not suppressed when the line has a comment
`total; # hide this` still displays the value.

### #12 Top-N revenue report times out
The top-10 report over 50,000 orders calls the currency-conversion callback
for every row. Only do the expensive work for rows that end up in the result.

### #13 Cross-field validation never runs
DTOs implement `validate(): ?string` (e.g. "end date before start date") but
`EntityValidator` never calls it.

### #14 Barcode of a deleted product cannot be reused
After deleting product A (barcode 400123), creating product B with barcode
400123 fails with "Barcode already taken".

### #15 PHP 8.4 deprecation warnings
Staging (PHP 8.4) logs "Implicitly marking parameter as nullable is
deprecated" for InvoicingService, DunningService and RemindersService.

### #16 Leaked more-specifics show as RPKI-valid
Our ROA is `198.51.100.0/22`, AS64500, maxLength 24. A leaked
`198.51.100.0/25` with our AS as origin is reported as valid. The validator
ignores maxLength; implement it per docs/RPKI.md.

### #17 Nightly snapshot job dies on reruns
When the nightly snapshot is re-run, `write('snapshot_<date>', $rows)`
throws. The data team wants the `ignore` save mode they use in Spark.

### #18 Same person indexed twice
Transcribers link "Smith, John" on page 3 and "smith,  John" on page 9 and
the collection now has two subjects for one person.

### #19 Approval never reaches `notify` when review is skipped
`submit -> review (only if amount > 1000) -> notify` plus `submit -> notify`.
For small amounts review is skipped and the workflow stops without
notifying anyone.

### #20 `>>` is rejected
`report --daily >> /var/log/report.log` fails with "Missing redirect
target". Support appending redirects.
