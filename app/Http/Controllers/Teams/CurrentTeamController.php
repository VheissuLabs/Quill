<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentTeamController extends Controller
{
    public function store(Request $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        abort_unless($request->user()->switchTeam($team), 403);

        return back();
    }
}
