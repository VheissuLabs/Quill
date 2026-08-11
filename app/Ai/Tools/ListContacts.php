<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ScopedToCurrentOrganization;
use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListContacts implements Tool
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

        /**
         * Read through the memberships rather than `members()` so the role comes
         * back as a cast enum instead of a raw pivot attribute.
         */
        $memberships = $organization->memberships()
            ->with(['user', 'client'])
            ->get()
            ->sortBy(fn (OrganizationMembership $membership) => $membership->user->name);

        if ($memberships->isEmpty()) {
            return "{$organization->name} has no members yet.";
        }

        return $memberships
            ->map(function (OrganizationMembership $membership): string {
                $user = $membership->user;
                $role = $membership->role;

                $kind = $role === OrganizationRole::Client
                    ? 'contact for the client '.($membership->client->name ?? 'unknown')
                    : 'works for the organization';

                return "- {$user->name} <{$user->email}> — {$role->label()}, {$kind}";
            })
            ->prepend("People in {$organization->name}:")
            ->join("\n");
    }

    /** @return array<string, \Illuminate\JsonSchema\Types\Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
