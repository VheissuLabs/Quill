<?php

namespace App\Http\Controllers\Sprints;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SprintStateController extends Controller
{
    public function store(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        $sprint->start();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint started.')]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Sprint $sprint): RedirectResponse
    {
        $sprint->complete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint completed.')]);

        return back();
    }
}
