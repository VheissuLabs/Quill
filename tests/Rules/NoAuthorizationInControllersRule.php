<?php

namespace Tests\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<MethodCall> */
class NoAuthorizationInControllersRule implements Rule
{
    private const FORBIDDEN = ['can', 'cannot', 'hasOrganizationPermission'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();

        if ($class === null || ! str_starts_with($class->getName(), 'App\\Http\\Controllers\\')) {
            return [];
        }

        if (! $node->name instanceof Node\Identifier || ! in_array($node->name->toString(), self::FORBIDDEN, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message("Controllers do not decide authorization. Move {$node->name->toString()}() to a route middleware, a policy, or the resource that renders the value.")
                ->identifier('quill.authorizationInController')
                ->build(),
        ];
    }
}
