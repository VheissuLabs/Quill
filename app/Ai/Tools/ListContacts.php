<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListContacts implements AssistantTool
{
    use ScopedToCurrentOrganization;

    public function name(): string
    {
        return 'list_contacts';
    }

    public function description(): Stringable|string
    {
        return 'List everyone who belongs to the organization the user is currently working in, with each person\'s role. People with the Client role are client contacts; everyone else works for the organization. Use this to answer any question about people, contacts, or members.';
    }

    public function handle(Request $request): Stringable|string
    {
        $organization = $this->organization();

        if ($organization === null) {
            return $this->withoutOrganization();
        }

        $memberships = $organization->memberships()
            ->with(['user', 'client'])
            ->get()
            ->sortBy(fn (OrganizationMembership $membership) => $membership->user->name);

        if ($memberships->isEmpty()) {
            return "{$organization->name} has no members yet.";
        }

        $roles = $this->roleNamesByUser($organization);

        return $memberships
            ->map(function (OrganizationMembership $membership) use ($roles): string {
                $user = $membership->user;
                $role = $roles->get($user->id);

                $kind = $membership->client_id === null
                    ? 'works for the organization'
                    : 'contact for the client '.($membership->client->name ?? 'unknown');

                return "- {$user->name} <{$user->email}> — ".Str::headline($role ?? 'no role').", {$kind}";
            })
            ->prepend("People in {$organization->name}:")
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * One query for every member's role, rather than a scoped lookup per person.
     *
     * @return Collection<string, string>
     */
    protected function roleNamesByUser(Organization $organization): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.organization_id', $organization->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->pluck('roles.name', 'model_has_roles.model_id');
    }
}
