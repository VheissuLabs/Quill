---
paths:
  - composer.json
  - phpstan.neon
---

# General

## composer ide-helper must end with @lint or CI breaks
The `ide-helper` script must keep `@lint` as its final step.

Why: `ide-helper:models -M` rewrites model class docblocks in expanded multi-line form. pint.json sets `phpdoc_line_span.class = single`, and the CI workflow runs `composer setup` (which calls `@ide-helper`) before `composer ci:check` (which runs `pint --test`). Without the trailing `@lint`, regeneration leaves files that fail the lint gate on every push.

Related: `no_blank_lines_after_phpdoc` is disabled in pint.json so the blank line between a model's `@mixin` docblock and its attribute survives.

## PHPStan stays at level 8 — do not raise it
Level 8 is a deliberate ceiling, not a waypoint. Measured on this codebase:

- Level 8: 1 error, a genuine bug (`$passkey->created_at->diffForHumans()` on a nullable Carbon).
- Level 9: +7 errors, all caused by Laravel returning `mixed` by design — `config()`, `once()`, `pluck()`, and Collection chains losing element types through `->map()->toArray()`.
- Level 10: +3 more, all framework surface (`Route::inertia()->name()`, `validationData()` overrides).

Levels 9-10 found zero bugs here and can only be satisfied by writing less idiomatic Laravel: replacing Collection chains with nested array_map/array_filter, dropping `once()`, or casting every `config()` call.

Level 8 keeps null-safety, which is where the real defects are. If 9+ is ever wanted, the fix belongs upstream in larastan (better `pluck()`/`once()` types), not in application code.

## PHPStan stays at level 7 (supersedes the earlier level 8 note)
Level 7 is a deliberate ceiling. An earlier rule here said level 8 — that was wrong and is superseded.

Measured on this codebase:
- Level 7: 0 errors, no code changes.
- Level 8: 39 errors, 0 genuine bugs. Every one is a framework invariant the schema cannot express: `auth()->user()` behind auth middleware (~25), and `created_at`/`updated_at` typed nullable because `$table->timestamps()` creates nullable columns that Eloquent always fills.
- Levels 9-10: +10 more, all Laravel returning `mixed` by design (`config()`, `once()`, `pluck()`, Collection chains, `Route::inertia()`).

Satisfying level 8 requires wrapping every `$request->user()` in a typed accessor and null-guarding timestamps that are never null. That is changing the code to suit the analyser, not fixing defects.

If level 8 is ever revisited, the entry cost is the ~25-site `currentUser()` refactor — measure whether it has found a real bug before paying it again.

## Verify with composer ci:check, not composer test
`composer test` only covers the PHP side (pint, phpstan, pest). CI runs `composer ci:check`, which additionally runs `npm run lint:check` (eslint), `npm run format:check` (prettier over resources/) and `npm run types:check` (vue-tsc) *before* `@test`.

So a branch can be green locally under `composer test` and still fail CI on frontend formatting alone — that happened on PR #1, where three .vue files failed `prettier --check` after a `composer test` run reported everything passing.

Before pushing anything that touches resources/, run `composer ci:check`. To fix formatting, run `npm run format` (prettier --write).
