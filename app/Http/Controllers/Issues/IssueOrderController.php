<?php

namespace App\Http\Controllers\Issues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\UpdateIssueOrderRequest;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IssueOrderController extends Controller
{
    public function update(UpdateIssueOrderRequest $request, Organization $currentOrganization): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $issues = Issue::whereIn('id', $request->validated('issue_ids'))->get()->keyBy('id');

            foreach (array_values($request->validated('issue_ids')) as $position => $issueId) {
                $issues[$issueId]->update(['position' => $position]);
            }
        });

        return back();
    }
}
