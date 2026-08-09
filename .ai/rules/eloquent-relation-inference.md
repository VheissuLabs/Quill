---
paths:
  - 'packages/eloquent-relation-inference/**'
---

# Eloquent Relation Inference

## Don't extend the package to array return types
This was tried and measured. A DynamicMethodReturnTypeExtension CAN recover an `: array` element type from a literal body — proven on FormRequest::rules().

It was rejected because coverage is ~20%. The extension receives the CALLER's Scope, not the method body's, so it can only evaluate scope-independent expressions. Of 15 array-returning methods here, 12 reference `$this`, locals or function calls (`fake()->name()`, `$this->passwordRules()`) and fall back to null.

Partial coverage is worse than none here: you delete one docblock successfully and the next method silently still needs one, with nothing indicating which.

Relation inference works because `belongsTo(Team::class)` is a bare class constant — scope-independent by nature. That property is what makes the technique viable, not the fact that it's "in the body".
