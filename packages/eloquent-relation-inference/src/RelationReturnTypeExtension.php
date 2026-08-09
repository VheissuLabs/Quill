<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Parameterises Eloquent relation methods from their body.
 *
 * A relation method already names its target in real code:
 *
 *     public function team(): BelongsTo
 *     {
 *         return $this->belongsTo(Team::class);
 *     }
 *
 * PHPStan does not read the body, so without a `@return BelongsTo<Team, $this>`
 * docblock the relation degrades to `Model` and chains like `$model->team()->first()`
 * silently lose their type. This extension reads the body instead, making the
 * docblock unnecessary.
 *
 * `missingType.generics` still fires on the declaration - that is a rule about what
 * is written, which no type extension can satisfy. Suppress it by identifier.
 */
final class RelationReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * Relation builder method => [relation class, takes an intermediate model].
     *
     * `morphTo` is deliberately absent: it has no single related class.
     *
     * @var array<string, array{class-string, bool}>
     */
    private const RELATIONS = [
        'belongsTo' => [BelongsTo::class, false],
        'hasOne' => [HasOne::class, false],
        'hasMany' => [HasMany::class, false],
        'belongsToMany' => [BelongsToMany::class, false],
        'hasOneThrough' => [HasOneThrough::class, true],
        'hasManyThrough' => [HasManyThrough::class, true],
    ];

    /** @var array<string, array{class-string, string, string|null}|null> */
    private array $resolved = [];

    /** @var array<string, array<int, Node\Stmt>> */
    private array $parsedFiles = [];

    public function __construct(private Parser $parser) {}

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->resolve($methodReflection) !== null;
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $resolved = $this->resolve($methodReflection);

        if ($resolved === null) {
            return null;
        }

        [$relationClass, $relatedClass, $intermediateClass] = $resolved;

        $declaringType = $scope->getType($methodCall->var);

        // Through-relations are <TRelatedModel, TIntermediateModel, TDeclaringModel>;
        // everything else is <TRelatedModel, TDeclaringModel> with later parameters
        // falling back to their declared defaults.
        $arguments = $intermediateClass !== null
            ? [new ObjectType($relatedClass), new ObjectType($intermediateClass), $declaringType]
            : [new ObjectType($relatedClass), $declaringType];

        return new GenericObjectType($relationClass, $arguments);
    }

    /**
     * Resolve a relation method to its relation class and target model(s).
     *
     * Returns null whenever the body cannot be read statically, in which case
     * PHPStan falls back to the declared return type and any docblock on it.
     *
     * @return array{class-string, string, string|null}|null
     */
    private function resolve(MethodReflection $methodReflection): ?array
    {
        $declaring = $methodReflection->getDeclaringClass();

        if (! $declaring->is(Model::class)) {
            return null;
        }

        $cacheKey = $declaring->getName().'::'.$methodReflection->getName();

        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }

        return $this->resolved[$cacheKey] = $this->doResolve($methodReflection);
    }

    /** @return array{class-string, string, string|null}|null */
    private function doResolve(MethodReflection $methodReflection): ?array
    {
        $returnType = $methodReflection->getVariants()[0]->getReturnType();

        if (! new ObjectType(Relation::class)->isSuperTypeOf($returnType)->yes()) {
            return null;
        }

        $native = $methodReflection->getDeclaringClass()->getNativeReflection();

        if (! $native->hasMethod($methodReflection->getName())) {
            return null;
        }

        // For a trait method the declaring ClassReflection is the *using* class, so
        // the body lives in the trait's file rather than the model's.
        $fileName = $native->getMethod($methodReflection->getName())->getFileName();

        if ($fileName === false) {
            return null;
        }

        $method = (new NodeFinder)->findFirst(
            $this->parseFile($fileName),
            static fn (Node $node): bool => $node instanceof ClassMethod
                && $node->name->toString() === $methodReflection->getName(),
        );

        if (! $method instanceof ClassMethod) {
            return null;
        }

        $call = (new NodeFinder)->findFirst(
            $method,
            static fn (Node $node): bool => $node instanceof MethodCall
                && $node->name instanceof Node\Identifier
                && isset(self::RELATIONS[$node->name->toString()]),
        );

        if (! $call instanceof MethodCall || ! $call->name instanceof Node\Identifier) {
            return null;
        }

        if (! isset($call->args[0]) || ! $call->args[0] instanceof Node\Arg) {
            return null;
        }

        $relatedClass = $this->classNameFrom($call->args[0]->value);

        if ($relatedClass === null) {
            return null;
        }

        [$relationClass, $takesIntermediate] = self::RELATIONS[$call->name->toString()];

        if (! $takesIntermediate) {
            return [$relationClass, $relatedClass, null];
        }

        if (! isset($call->args[1]) || ! $call->args[1] instanceof Node\Arg) {
            return null;
        }

        $intermediateClass = $this->classNameFrom($call->args[1]->value);

        return $intermediateClass === null
            ? null
            : [$relationClass, $relatedClass, $intermediateClass];
    }

    /** @return array<int, Node\Stmt> */
    private function parseFile(string $fileName): array
    {
        return $this->parsedFiles[$fileName] ??= $this->parser->parseFile($fileName);
    }

    /** Resolve a `Foo::class` expression to its fully qualified name. */
    private function classNameFrom(Node\Expr $expr): ?string
    {
        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Node\Name) {
            return null;
        }

        $resolved = $expr->class->getAttribute('resolvedName');

        return $resolved instanceof Node\Name
            ? $resolved->toString()
            : $expr->class->toString();
    }
}
