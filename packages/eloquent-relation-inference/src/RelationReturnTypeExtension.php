<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
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
 * silently lose their type. This reads the body instead, making the docblock
 * unnecessary.
 *
 * `missingType.generics` still fires on the declaration — that is a rule about what is
 * written, which no type extension can satisfy. Suppress it by identifier.
 */
final class RelationReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private RelationBodyParser $parser) {}

    public function getClass(): string
    {
        return Model::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $this->parser->parse($methodReflection) !== null;
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type {
        $relation = $this->parser->parse($methodReflection);

        if ($relation === null) {
            return null;
        }

        return new GenericObjectType(
            $relation->relationClass,
            $this->templateArguments($relation, $scope->getType($methodCall->var)),
        );
    }

    /**
     * Through-relations are <TRelatedModel, TIntermediateModel, TDeclaringModel>;
     * everything else is <TRelatedModel, TDeclaringModel>, with any later parameters
     * falling back to their declared defaults.
     *
     * @return array<int, Type>
     */
    private function templateArguments(ResolvedRelation $relation, Type $declaringType): array
    {
        if (! $relation->isThrough()) {
            return [
                new ObjectType($relation->relatedClass),
                $declaringType,
            ];
        }

        return [
            new ObjectType($relation->relatedClass),
            new ObjectType((string) $relation->intermediateClass),
            $declaringType,
        ];
    }
}
