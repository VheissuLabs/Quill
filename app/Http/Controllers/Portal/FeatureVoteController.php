<?php

namespace App\Http\Controllers\Portal;

use App\Enums\VoteDirection;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FeatureVoteController extends Controller
{
    public function store(Request $request, Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        Gate::authorize('vote', $feature);

        $direction = VoteDirection::from($request->validate([
            'direction' => ['sometimes', 'string', Rule::enum(VoteDirection::class)],
        ])['direction'] ?? VoteDirection::Up->value);

        $feature->castVote($request->user(), $direction);

        return back();
    }

    public function destroy(Request $request, Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        Gate::authorize('vote', $feature);

        $feature->votes()->where('user_id', $request->user()->id)->delete();

        return back();
    }
}
