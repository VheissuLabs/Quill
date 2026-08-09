# Eloquent Relation Inference

A PHPStan extension that reads Eloquent relation generics out of the method body, so
relation methods need no `@return` docblock.

## The problem

```php
/** @return BelongsTo<Team, $this> */
public function team(): BelongsTo
{
    return $this->belongsTo(Team::class);
}
```

The docblock restates the line below it. PHPStan never reads method bodies, so without
it the relation degrades to `Model` and chains like `$model->team()->first()` silently
return the wrong type.

With this extension the docblock is unnecessary:

```php
public function team(): BelongsTo
{
    return $this->belongsTo(Team::class);
}
```

## Install

```neon
# phpstan.neon
includes:
    - vendor/quill/eloquent-relation-inference/extension.neon

parameters:
    ignoreErrors:
        - identifier: missingType.generics
          message: '#return type with generic class Illuminate\\Database\\Eloquent\\Relations#'
```

Include order relative to larastan does not matter — this is a dynamic *return type*
extension, so it does not compete with larastan's properties extension.

The `ignoreErrors` entry is required. `missingType.generics` inspects the declaration,
not the resolved type, so no type extension can satisfy it. Identifier-based ignoring
is PHPStan's own documented replacement for the removed
`checkGenericClassInNonGenericObjectType` parameter.

## What it covers

`belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`.

`morphTo` is deliberately excluded — it has no single related class, so keep the
docblock on those methods.

Resolution requires the related model to be a literal `Foo::class` argument. Anything
the extension cannot read statically — a computed class name, a conditional return —
returns null and falls back to PHPStan's normal behaviour, so a docblock still works.

Trait methods are handled: the body is located via native reflection, since PHPStan
reports the *using* class as the declaring class for a trait method.

## What it does not do

Property access (`$model->team->name`) is already typed by laravel-ide-helper's
generated mixin, which derives relations by reflecting the booted application. This
extension exists only for relation *builder chains*.

## Performance

Larastan did this until 3.0 and removed it as "slow because it required parsing the
file". That objection is real and was measured here: a naive implementation added
**+54%** to analysis time (7.3s to 11.3s on a 60-file application).

The cost was repeated parsing, not parsing. `isMethodSupported()` runs for every method
call on any Model, and the original implementation re-parsed the file every time.
Measured:

| Configuration | Time | Overhead |
| --- | --- | --- |
| No memoisation | 11.26s | +3.95s |
| Memoised resolution | 7.43s | +0.12s |
| Plus return-type guard and `defaultAnalysisParser` | 7.32s | ~0 |
| Extension disabled (baseline) | 7.31s | — |

Memoisation accounts for 97% of the fix. The return-type guard — bailing before the
parse when the declared return type is not a `Relation` — is worth about 3%.

Two deliberate choices about memory:

`$resolved` memoises three strings per relation method. It is bounded in practice by
the number of relation methods in the project.

Parsing is delegated to PHPStan's `defaultAnalysisParser`, which is a `CachedParser`
with `cachedNodesByStringCountMax` and `cachedSourceBytesMax` limits. An earlier
version cached syntax trees in a local array, which was 1.4% faster and grew without
bound — that trade is wrong on a large codebase, which is presumably why larastan
injected the uncached `currentPhpVersionSimpleDirectParser` rather than adding its own
cache on top.

Re-measure before trusting this on a substantially larger codebase.

## Status

No unit tests yet. Currently exercised end to end by the host application: 11 relation
methods with no docblocks, analysed at level 7 in CI. A regression in the extension
turns those chains back into `Model` and fails the build.
