<?php

namespace Tests\Rules;

use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<StaticCall> */
class UnsafeResourceCollectionRule implements Rule
{
    public function __construct(private ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier || $node->name->toString() !== 'collection') {
            return [];
        }

        if (! $node->class instanceof Node\Name) {
            return [];
        }

        $name = $scope->resolveName($node->class);

        if (! $this->reflectionProvider->hasClass($name)) {
            return [];
        }

        $class = $this->reflectionProvider->getClass($name);

        if (! in_array(JsonResource::class, $class->getParentClassesNames(), true)) {
            return [];
        }

        if (! $class->hasConstructor()) {
            return [];
        }

        if (count($class->getConstructor()->getVariants()[0]->getParameters()) < 2) {
            return [];
        }

        return [
            RuleErrorBuilder::message("{$name}::collection() passes the array index as the second constructor argument. Give {$name} a single-argument constructor and put the variant in a subclass.")
                ->identifier('quill.unsafeResourceCollection')
                ->build(),
        ];
    }
}
