<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    public function handle(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name) {
            $organization = Organization::create(['name' => $name]);

            $organization->addMember($user, $organization->ownerRole());

            $user->switchOrganization($organization);

            return $organization;
        });
    }
}
