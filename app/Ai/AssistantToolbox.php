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

        $permitted = fn (string $permission): bool => $user->can($permission);

        if ($permitted('client:create')) {
            $tools[] = new CreateClient($user);
        }

        if ($permitted('client:update')) {
            $tools[] = new RenameClient($user);
        }

        if ($permitted('team:create')) {
            $tools[] = new CreateTeam($user);
        }

        if ($permitted('team:update')) {
            $tools[] = new RenameTeam($user);
        }

        if ($permitted('project:create')) {
            $tools[] = new CreateProject($user);
        }

        if ($permitted('project:update')) {
            $tools[] = new RenameProject($user);
        }

        if ($permitted('member:add')) {
            $tools[] = new CreateContact($user);
        }

        $tools[] = new ListCapabilities($user, $tools);

        return $tools;
    }
}
