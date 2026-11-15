<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\WorkTypeRequest;
use App\Models\Organization;
use App\Models\WorkType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkTypeController extends Controller
{
    public function store(WorkTypeRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->workTypes()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work type added.')]);

        return back();
    }

    public function update(WorkTypeRequest $request, Organization $currentOrganization, WorkType $workType): RedirectResponse
    {
        $workType->update($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, WorkType $workType): RedirectResponse
    {
        $workType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Work type deleted.')]);

        return back();
    }
}
