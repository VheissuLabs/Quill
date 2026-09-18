<?php

namespace App\Http\Controllers\Portal;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\UpdateFeedbackStatusRequest;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeedbackStatusController extends Controller
{
    public function update(UpdateFeedbackStatusRequest $request, Organization $organization, Project $project, Feedback $feedback): RedirectResponse
    {
        $feedback->update(['status' => FeedbackStatus::from($request->validated('status'))]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }
}
