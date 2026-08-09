# Relation Type Inference Without Docblocks

**Date:** 2026-08-08
**Status:** Built and shipped as `packages/eloquent-relation-inference`

This document was written as a design and has been rewritten to match what was
actually built. Several of the original assumptions were wrong; they are recorded
below because the reasoning matters more than the conclusion.

## Problem

Laravel relation methods required a PHPDoc generic restating information already in
the method body:

```php
/** @return BelongsTo<Team, $this> */
public function team(): BelongsTo
{
    return $this->belongsTo(Team::class);
}
```

The docblock adds nothing a reader or a machine cannot see one line below. It must be
maintained by hand, and when the relation target changes and the docblock does not, it
becomes a lie the analyser believes over the code.

This codebase carried 11 such docblocks across four models and one trait.

## What was built

A PHPStan `DynamicMethodReturnTypeExtension` that parses the relation method body,
reads the `Foo::class` argument, and parameterises the return type at the call site.

Covers `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, `hasOneThrough`,
`hasManyThrough`. `morphTo` is excluded — it has no single related class.

Returns null whenever the body cannot be read statically, falling back to PHPStan's
normal behaviour, so a docblock still works where inference cannot reach.

`phpstan.neon` carries a scoped `ignoreErrors` entry for `missingType.generics` on
Eloquent relation classes. That rule inspects the *declaration*, not the resolved
type, so no extension can satisfy it. Identifier-based ignoring is PHPStan's own
documented replacement for the removed `checkGenericClassInNonGenericObjectType`.

## Verified

- 11 docblocks deleted → 30 errors without the extension → **0 with it**.
- Include order relative to larastan is irrelevant.
- Trait relation methods work: PHPStan reports the *using* class as the declaring
  class, so the body is located via native reflection instead.

## Assumptions that proved wrong

**A properties extension is needed.** It is not. laravel-ide-helper's generated mixin
already types property access (`$model->team->name`) by reflecting the booted
application. Only relation *builder chains* (`$model->team()->first()`) needed help.
The package is half the originally designed size.

**Include order is load-bearing.** True for a properties extension, which competes
with larastan's `ModelRelationsExtension`. False for a return-type extension. The
README instruction the design was built around is unnecessary.

**larastan reads `#[UseFactory]` to satisfy the `HasFactory` generic.** It does not.
That generic is handled by a separate scoped ignore.

**The `@return` docblock is load-bearing for property access.** Measured on scratch
classes that had no ide-helper mixin, which is why it looked that way.

## Deliberately not built

**Array return type inference.** A `DynamicMethodReturnTypeExtension` can recover an
`: array` element type from a literal body — proven on `FormRequest::rules()`. Rejected
at ~20% coverage: the extension receives the *caller's* Scope, so it can only evaluate
scope-independent expressions. Of 15 array-returning methods here, 12 reference `$this`,
locals, or function calls. Partial coverage is worse than none, because a deleted
docblock succeeds in one method and silently fails in the next.

Relation inference works precisely because `belongsTo(Team::class)` is a bare class
constant. That property, not "being in the body", is what makes the technique viable.

**`@extends Factory<X>` inference.** Removing those docblocks produced real type loss,
not just nags. The generic must reach subclass consumers through the declaration.

## Open

- No unit tests. The host application is the regression guard: 11 docblock-free
  relation methods analysed at level 7 in CI. A regression turns those chains back
  into `Model` and fails the build.
- Package name `quill/eloquent-relation-inference` is provisional.
