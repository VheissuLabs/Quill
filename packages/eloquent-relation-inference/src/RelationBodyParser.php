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
use PHPStan\Parser\Parser;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;

/**
 * Reads the related model out of a relation method's body.
 *
 * Results are memoised per class::method. What is cached is three strings, not the
 * syntax tree — parsing is delegated to PHPStan's own bounded CachedParser.
 */
final class RelationBodyParser
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

    /** @var array<string, ResolvedRelation|null> */
    private array $resolved = [];

    public function __construct(private Parser $parser) {}

    public function parse(MethodReflection $methodReflection): ?ResolvedRelation
    {
        $declaring = $methodReflection->getDeclaringClass();

        if (! $declaring->is(Model::class)) {
            return null;
        }

        $key = $declaring->getName().'::'.$methodReflection->getName();

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = $this->read($methodReflection);
    }

    private function read(MethodReflection $methodReflection): ?ResolvedRelation
    {
        if (! $this->returnsRelation($methodReflection)) {
            return null;
        }

        $method = $this->findMethodNode($methodReflection);

        if ($method === null) {
            return null;
        }

        $call = $this->findRelationCall($method);

        if ($call === null || ! $call->name instanceof Node\Identifier) {
            return null;
        }

        [$relationClass, $takesIntermediate] = self::RELATIONS[$call->name->toString()];

        $relatedClass = $this->argumentClassName($call, 0);

        if ($relatedClass === null) {
            return null;
        }

        if (! $takesIntermediate) {
            return new ResolvedRelation($relationClass, $relatedClass);
        }

        $intermediateClass = $this->argumentClassName($call, 1);

        return $intermediateClass === null
            ? null
            : new ResolvedRelation($relationClass, $relatedClass, $intermediateClass);
    }

    /**
     * Cheap reflection check, run before parsing anything. `isMethodSupported()` is
     * called for every method on every model, so bailing here keeps `save()`,
     * `where()` and the rest from triggering a parse.
     */
    private function returnsRelation(MethodReflection $methodReflection): bool
    {
        $returnType = $methodReflection->getVariants()[0]->getReturnType();

        return new ObjectType(Relation::class)->isSuperTypeOf($returnType)->yes();
    }

    private function findMethodNode(MethodReflection $methodReflection): ?ClassMethod
    {
        $native = $methodReflection->getDeclaringClass()->getNativeReflection();
        $name = $methodReflection->getName();

        if (! $native->hasMethod($name)) {
            return null;
        }

        // For a trait method the declaring ClassReflection is the *using* class, so
        // the body lives in the trait's file rather than the model's.
        $fileName = $native->getMethod($name)->getFileName();

        if ($fileName === false) {
            return null;
        }

        $node = new NodeFinder()->findFirst(
            $this->parser->parseFile($fileName),
            static fn (Node $n): bool => $n instanceof ClassMethod && $n->name->toString() === $name,
        );

        return $node instanceof ClassMethod ? $node : null;
    }

    private function findRelationCall(ClassMethod $method): ?MethodCall
    {
        $call = new NodeFinder()->findFirst(
            $method,
            static fn (Node $n): bool => $n instanceof MethodCall
                && $n->name instanceof Node\Identifier
                && isset(self::RELATIONS[$n->name->toString()]),
        );

        return $call instanceof MethodCall ? $call : null;
    }

    /** @return class-string|null */
    private function argumentClassName(MethodCall $call, int $position): ?string
    {
        if (! isset($call->args[$position]) || ! $call->args[$position] instanceof Node\Arg) {
            return null;
        }

        $expr = $call->args[$position]->value;

        if (! $expr instanceof ClassConstFetch || ! $expr->class instanceof Node\Name) {
            return null;
        }

        $resolved = $expr->class->getAttribute('resolvedName');

        /** @var class-string */
        return $resolved instanceof Node\Name
            ? $resolved->toString()
            : $expr->class->toString();
    }
}
