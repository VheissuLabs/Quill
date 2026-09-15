<?php

namespace App\Http\Controllers\Issues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\UpdateIssueOrderRequest;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class IssueOrderController extends Controller
{
    public function update(UpdateIssueOrderRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('viewAny', [Issue::class, $currentOrganization]);

        DB::transaction(function () use ($request) {
            $issues = Issue::whereIn('id', $request->validated('issue_ids'))->get()->keyBy('id');

            foreach (array_values($request->validated('issue_ids')) as $position => $issueId) {
                Gate::authorize('update', $issues[$issueId]);

                $issues[$issueId]->update(['position' => $position]);
            }
        });

        return back();
    }
}
