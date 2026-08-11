<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListCapabilities implements AssistantTool
{
    use ScopedToCurrentOrganization;

    /** @param list<AssistantTool> $granted */
    public function __construct(protected User $user, protected array $granted = []) {}

    public function name(): string
    {
        return 'list_capabilities';
    }

    public function capability(): string
    {
        return 'Tell you what I can do for you.';
    }

    public function description(): Stringable|string
    {
        return 'List what you are able to do for this user. Call this whenever you are asked what you can do, what your tools are, how you can help, or what you have access to. Report the list as given — it already reflects what this user is allowed to do.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();
        $role = $organization === null ? null : $this->user->organizationRole($organization);

        $lines = collect($this->granted)
            ->map(fn (AssistantTool $tool) => '- '.$tool->capability())
            ->push('- '.$this->capability());

        $heading = $organization === null
            ? 'Here is what I can do:'
            : "Here is what I can do for you in {$organization->name}".
                ($role === null ? '' : " as {$role->label()}").':';

        return $lines
            ->prepend($heading)
            ->push('Renaming is the only change I can make — I cannot delete anything. Projects and issues do not exist in Quill yet.')
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
