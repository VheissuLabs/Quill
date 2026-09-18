<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\CastVoteRequest;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class FeatureVoteController extends Controller
{
    public function store(CastVoteRequest $request, Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        $feature->castVote($request->user(), $request->direction());

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        $feature->clearVoteFrom(auth()->user());

        return back();
    }
}
