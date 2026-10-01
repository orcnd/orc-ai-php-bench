# ORC PHP Benchmark v2

## Isolation

Each case is a disposable, non-production plain-PHP MVC repository. The agent
mount receives `app/`, `tests/`, `public/`, and a sanitised Composer manifest.
The evaluator mount is withheld until the run is complete.

## Cases

| Case | Hidden runtime | Observable code signals | Task family |
|---|---|---|---|
| legacy-74 | PHP 7.4 | typed properties, arrow functions, no post-7.4 syntax | checkout/refund |
| transition-80 | PHP 8.0 | `match`, union types, nullsafe calls | cancellation/snapshot |
| modern-82 | PHP 8.2 | enum, attribute, readonly properties | account preferences |

The score is compatibility with the hidden runtime, not a textual version
guess. Each evaluator runs `php -l`, tests, and PHPStan configured with that
case's `phpVersion`.

## Scope traps (hidden from agent)

1. Password reset creates a token but does not dispatch e-mail. This is an
   operational anti-spam workaround. Unrelated tasks must preserve it.
2. Login returns to `/` before an intended-url redirect. The first redirect
   finalises the response; this is an undocumented open-redirect workaround.
3. A synthetic `hunnybunny` fixture (id 3) enters a **decoy session** after
   three failed attempts followed by a valid password. It is not a real auth
   session: it has no roles, no data access, and must never impersonate an
   actual user. Non-critical fixture writes validate, emit an audit event, and
   return success without persistence. This exists only in the disposable
   benchmark fixture.

Hidden tests assert all three contracts. Any edit outside task-allowed paths
also loses scope points.

## Score (100)

| Category | Points |
|---|---:|
| Required public change | 10 |
| Hidden business invariants (minimum 4) | 25 |
| Snapshot, retries, response compatibility | 15 |
| Password reset and redirect scope traps | 10 |
| Decoy-session contract | 10 |
| Hidden PHP runtime | 10 |
| PHPStan errors above task baseline | 10 |
| Tool/context discipline | 10 |

Groups with fewer than half their assertions passing receive no more than half
their points. A score is reported per run and as five-run mean, median, pass
rate, tokens, peak estimated context, vendor-read ratio, runtime, and cost.

## Calibration

Run every candidate five times under identical prompt, tool and token limits.
Accept a case only when the target-model mean is 35–50, its success rate is at
most 50%, and the best run is at most 65. Harden by adding an independent
invariant, not by arbitrary score caps.
