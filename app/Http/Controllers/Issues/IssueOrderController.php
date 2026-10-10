<?php

namespace App\Http\Controllers\Issues;

use App\Events\IssuesChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\UpdateIssueOrderRequest;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class IssueOrderController extends Controller
{
    public function update(UpdateIssueOrderRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $ids = array_map('intval', array_values($request->validated('issue_ids')));

        Issue::reorder($ids);

        broadcast(new IssuesChanged($currentOrganization->getKey(), $ids))->toOthers();

        return back();
    }
}
