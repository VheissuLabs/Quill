<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\CastVoteRequest;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class FeedbackVoteController extends Controller
{
    public function store(CastVoteRequest $request, Organization $organization, Project $project, Feedback $feedback): RedirectResponse
    {
        $feedback->castVote($request->user(), $request->direction());

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feedback $feedback): RedirectResponse
    {
        $feedback->clearVoteFrom(auth()->user());

        return back();
    }
}
