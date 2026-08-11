<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DescribeOrganization implements Tool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'describe_organization';
    }

    public function description(): Stringable|string
    {
        return 'Get the name of the organization the user is currently working in, the user\'s role in it, and how many clients, teams, and members it has. Use this to answer any question about the organization itself.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $role = $this->user->organizationRole($organization);

        return implode("\n", [
            "Organization: {$organization->name}",
            'The user\'s role: '.($role?->label() ?? 'unknown'),
            'Clients: '.$organization->clients()->count(),
            'Teams: '.$organization->teams()->count(),
            'Members: '.$organization->members()->count(),
        ]);
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
