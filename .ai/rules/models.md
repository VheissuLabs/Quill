---
paths:
  - 'app/Models/*.php'
---

# Models

## Use $guarded and #[UseFactory], never $fillable or #[Fillable]
Mass assignment is declared with `protected $guarded = ['id', 'created_at', 'updated_at']` (plus 'deleted_at' on soft-deleting models). Never `$fillable`, and never the `#[Fillable]` attribute.

Factories are declared with `#[UseFactory(XFactory::class)]` on the class, keeping the `HasFactory` trait.

Why: schema-shaped lists (columns) belong in properties next to `casts()`; attributes are reserved for config that points at another class. `Team::class` in an attribute is a real, rename-safe reference; a column list in a class-header attribute is not.

## Which model docblocks are actually required
Do NOT add these — they are redundant and were deliberately removed:
- `/** @use HasFactory<XFactory> */` — `#[UseFactory]` already names the factory; phpstan.neon ignores the trait-generic error for app/Models.
- `/** @var list<string> */` above `$guarded` — measured as optional at levels 7, 9 and 10.

DO keep `/** @mixin IdeHelperX */` (one line, blank line after it before the attribute/class).

Non-obvious: `/** @return BelongsTo<Team, $this> */` on relation methods is NOT needed for property access — ide-helper's generated mixin declares `@property-read Team|null $team` by reflecting the booted app. It IS still needed for relation builder chains (`$model->team()->first()`), which otherwise silently return `Model`.

## Relation methods take no @return docblock
Do NOT add `/** @return BelongsTo<Team, $this> */` to relation methods. The quill/eloquent-relation-inference PHPStan extension (packages/, wired in phpstan.neon) reads the related model from the body, and phpstan.neon ignores the resulting missingType.generics.

This supersedes the earlier note saying the docblock was still needed for builder chains — the extension now covers them.

Exception: `morphTo()` has no single related class, so it still needs a docblock.

Applies to relation methods in traits too (app/Concerns/HasTeams.php).

## Models carry exactly one docblock: @mixin
The only docblock a model should have is `/** @mixin IdeHelperX */` (one line, blank line after it, before the attribute or class).

All of these were measured as unnecessary at levels 7, 9 and 10 and must not be re-added:
- `@return array<string, string>` on `casts()` — inferred from the returned literal
- `@var list<string>` on `$guarded` and `$hidden`
- `@var bool` / `@var string` on `$incrementing`, `$table`
- `@use HasFactory<XFactory>` — see the #[UseFactory] rule
- `@return BelongsTo<...>` on relations — see the relation inference rule

Verify with `composer test` before assuming any docblock is load-bearing; this project measured each one rather than guessing.
