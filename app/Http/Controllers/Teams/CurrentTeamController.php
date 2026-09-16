<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class CurrentTeamController extends Controller
{
    public function store(Organization $currentOrganization, Team $team): RedirectResponse
    {
        abort_unless(auth()->user()->switchTeam($team), 403);

        return back();
    }
}
