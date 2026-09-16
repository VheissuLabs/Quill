<?php

namespace Tests\Rules;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<New_> */
class NoNewModelsRule implements Rule
{
    public function __construct(private ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Node\Name || $node->getArgs() === []) {
            return [];
        }

        $name = $scope->resolveName($node->class);

        if (! $this->reflectionProvider->hasClass($name)) {
            return [];
        }

        if (! in_array(Model::class, $this->reflectionProvider->getClass($name)->getParentClassesNames(), true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Do not construct {$name} directly. Create it through a relation, or Model::create().")
                ->identifier('quill.newModel')
                ->build(),
        ];
    }
}
