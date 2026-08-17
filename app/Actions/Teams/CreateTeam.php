<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    public function handle(User $user, string $name, bool $isPersonal = false): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
                'owner_id' => $user->id,
            ]);

            $team->memberships()->create(['user_id' => $user->id]);

            $user->switchTeam($team);

            return $team;
        });
    }
}
