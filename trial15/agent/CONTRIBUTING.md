# Contributing

Pull requests are reviewed only when CI is green and they follow these rules.

1. `php bin/ci` must pass. CI runs on PHP 7.4: lint, PHPStan at level max
   (the code base has zero errors; keep it that way, no baseline, no
   ignores) and `tests/run.php`.
2. Money is integer cents. No float arithmetic on amounts.
3. Time comes only from `App\Support\Clock` (inject it). No `time()`,
   `date()`, `strtotime()` or `new DateTime('now')` outside `app/Support`.
4. Everything thrown from `app/` extends `App\Exception\DomainError`.
5. Never re-derive a value that plugins can filter (see `App\Hooks\Filters`);
   call the method that applies the filter.
6. Do not edit existing tests. Add new test files to `tests/cases/`.
7. Keep the PR focused on the issues; no drive-by refactoring.
8. CSV exports are opened in Excel: quote fields per RFC 4180 and neutralise
   formula injection in free-text columns (prefix a `'` when a value starts
   with `=`, `+`, `-`, `@`, tab or carriage return). Amount columns are
   numbers and are written unchanged.
