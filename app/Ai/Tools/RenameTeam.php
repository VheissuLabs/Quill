<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RenameTeam implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'rename_team';
    }

    public function capability(): string
    {
        return 'Rename one of your teams.';
    }

    public function description(): Stringable|string
    {
        return 'Change the name of an existing team. Requires the team\'s current name and the new name. This only changes the name — it cannot move, merge or delete a team.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::UpdateTeam)) {
            return $this->refused('rename a team');
        }

        $newName = trim((string) $request['new_name']);

        if ($newName === '') {
            return 'A new name is needed. Ask the user what the team should be called.';
        }

        $team = $this->resolveTarget($organization, (string) $request['team']);

        if (is_string($team)) {
            return $team;
        }

        if ($this->comparableName($team->name) === $this->comparableName($newName)) {
            return "{$team->name} is already called that, so nothing was changed.";
        }

        $clash = $organization->teams()->get()->first(
            fn (Team $other) => ! $other->is($team)
                && $this->comparableName($other->name) === $this->comparableName($newName)
        );

        if ($clash !== null) {
            return "{$organization->name} already has a team called {$clash->name}, so nothing was renamed.";
        }

        $previous = $team->name;

        $team->update(['name' => $newName]);

        return "Renamed the team {$previous} to {$team->name}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'team' => $schema->string()->description('The current name of the team to rename.')->required(),
            'new_name' => $schema->string()->description('The name the team should have.')->required(),
        ];
    }

    protected function resolveTarget(Organization $organization, string $wanted): Team|string
    {
        $names = $organization->teams()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($names, $wanted, 'team');

        if ($matches->count() === 1) {
            return $organization->teams()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one team matches \"{$wanted}\": ".
                $matches->join(', ').'. Nothing was renamed — ask which one.';
        }

        return "There is no team called {$wanted} in {$organization->name}, so nothing was renamed. ".
            ($names->isEmpty()
                ? 'The organization has no teams at all.'
                : 'The teams are: '.$names->join(', ').'.');
    }
}
