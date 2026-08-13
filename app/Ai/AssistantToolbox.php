<?php

namespace App\Ai;

use App\Ai\Contracts\AssistantTool;
use App\Ai\Tools\CreateClient;
use App\Ai\Tools\CreateContact;
use App\Ai\Tools\CreateProject;
use App\Ai\Tools\CreateTeam;
use App\Ai\Tools\DescribeOrganization;
use App\Ai\Tools\ListCapabilities;
use App\Ai\Tools\ListClients;
use App\Ai\Tools\ListContacts;
use App\Ai\Tools\ListProjects;
use App\Ai\Tools\ListTeams;
use App\Ai\Tools\RenameClient;
use App\Ai\Tools\RenameProject;
use App\Ai\Tools\RenameTeam;
use App\Enums\OrganizationPermission;
use App\Models\User;

class AssistantToolbox
{
    /** @return list<AssistantTool> */
    public function for(User $user): array
    {
        $tools = [
            new DescribeOrganization($user),
            new ListClients($user),
            new ListTeams($user),
            new ListContacts($user),
            new ListProjects($user),
        ];

        $organization = $user->currentOrganization;

        $permitted = fn (OrganizationPermission $permission): bool => $organization !== null
            && $user->hasOrganizationPermission($organization, $permission);

        if ($permitted(OrganizationPermission::CreateClient)) {
            $tools[] = new CreateClient($user);
        }

        if ($permitted(OrganizationPermission::UpdateClient)) {
            $tools[] = new RenameClient($user);
        }

        if ($permitted(OrganizationPermission::CreateTeam)) {
            $tools[] = new CreateTeam($user);
        }

        if ($permitted(OrganizationPermission::UpdateTeam)) {
            $tools[] = new RenameTeam($user);
        }

        if ($permitted(OrganizationPermission::CreateProject)) {
            $tools[] = new CreateProject($user);
        }

        if ($permitted(OrganizationPermission::UpdateProject)) {
            $tools[] = new RenameProject($user);
        }

        if ($permitted(OrganizationPermission::AddMember)) {
            $tools[] = new CreateContact($user);
        }

        $tools[] = new ListCapabilities($user, $tools);

        return $tools;
    }
}
