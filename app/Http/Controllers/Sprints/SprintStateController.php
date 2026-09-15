<?php

namespace App\Http\Controllers\Sprints;

use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SprintStateController extends Controller
{
    public function store(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('update', $sprint);

        abort_unless($sprint->status === SprintStatus::Planned, 409, __('Only a planned sprint can be started.'));

        $sprint->start();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint started.')]);

        return back();
    }

    /**
     * Complete the sprint, returning unfinished issues to the backlog.
     */
    public function destroy(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('update', $sprint);

        abort_unless($sprint->status === SprintStatus::Active, 409, __('Only an active sprint can be completed.'));

        $sprint->complete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint completed.')]);

        return back();
    }
}
