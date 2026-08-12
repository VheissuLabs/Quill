<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\MatchesNames;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RenameProject implements AssistantTool
{
    use MatchesNames;
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'rename_project';
    }

    public function capability(): string
    {
        return 'Rename one of your projects.';
    }

    public function description(): Stringable|string
    {
        return 'Change the name of an existing project. Requires the project\'s current name and the new name. This only changes the name — it cannot move a project to another owner, merge or delete it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        if (! $this->user->hasOrganizationPermission($organization, OrganizationPermission::UpdateProject)) {
            return $this->refused('rename a project');
        }

        $newName = trim((string) $request['new_name']);

        if ($newName === '') {
            return 'A new name is needed. Ask the user what the project should be called.';
        }

        $project = $this->resolveTarget($organization, (string) $request['project']);

        if (is_string($project)) {
            return $project;
        }

        if ($this->comparableName($project->name) === $this->comparableName($newName)) {
            return "{$project->name} is already called that, so nothing was changed.";
        }

        $clash = $organization->projects()->get()->first(
            fn (Project $other) => ! $other->is($project)
                && $this->comparableName($other->name) === $this->comparableName($newName)
        );

        if ($clash !== null) {
            return "{$organization->name} already has a project called {$clash->name}, so nothing was renamed.";
        }

        $previous = $project->name;

        $project->update(['name' => $newName]);

        return "Renamed the project {$previous} to {$project->name}.";
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('The current name of the project to rename.')->required(),
            'new_name' => $schema->string()->description('The name the project should have.')->required(),
        ];
    }

    protected function resolveTarget(Organization $organization, string $wanted): Project|string
    {
        $names = $organization->projects()->orderBy('name')->pluck('name');
        $matches = $this->matchingNames($names, $wanted, 'project');

        if ($matches->count() === 1) {
            return $organization->projects()->where('name', $matches->sole())->sole();
        }

        if ($matches->count() > 1) {
            return "More than one project matches \"{$wanted}\": ".
                $matches->join(', ').'. Nothing was renamed — ask which one.';
        }

        return "There is no project called {$wanted} in {$organization->name}, so nothing was renamed. ".
            ($names->isEmpty()
                ? 'The organization has no projects at all.'
                : 'The projects are: '.$names->join(', ').'.');
    }
}
