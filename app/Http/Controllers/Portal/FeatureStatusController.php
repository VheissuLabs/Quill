<?php

namespace App\Http\Controllers\Portal;

use App\Enums\FeatureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Features\UpdateFeatureStatusRequest;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeatureStatusController extends Controller
{
    public function update(UpdateFeatureStatusRequest $request, Organization $organization, Project $project, Feature $feature): RedirectResponse
    {
        $feature->update(['status' => FeatureStatus::from($request->validated('status'))]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }
}
