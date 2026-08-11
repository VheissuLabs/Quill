<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListTeams implements Tool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'list_teams';
    }

    public function description(): Stringable|string
    {
        return 'List every team in the organization the user is currently working in, along with what each team belongs to: the organization directly, or one of its clients. Use this to answer any question about teams, and to check whether a team already exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $teams = $organization->teams()->with('parent')->orderBy('name')->get();

        if ($teams->isEmpty()) {
            return "{$organization->name} has no teams yet.";
        }

        return $teams
            ->map(function (Team $team): string {
                $parent = $team->parent;

                $belongsTo = match (true) {
                    $parent instanceof Client => "under the client {$parent->name}",
                    $parent instanceof Organization => 'under the organization directly',
                    default => 'with no recorded parent',
                };

                return "- {$team->name} ({$belongsTo}, ".$team->members()->count().' members)';
            })
            ->prepend("Teams in {$organization->name}:")
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
