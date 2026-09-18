<?php

namespace App\Http\Controllers\Issues;

use App\Enums\IssueLinkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\IssueLinkRequest;
use App\Http\Requests\Issues\UpdateIssueLinkRequest;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class IssueLinkController extends Controller
{
    public function update(UpdateIssueLinkRequest $request, Organization $currentOrganization, Issue $issue, IssueLink $link): RedirectResponse
    {
        $issue->recastLink($link, IssueLinkType::from($request->validated('type')), $request->boolean('inverse'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link updated.')]);

        return back();
    }

    public function destroy(IssueLinkRequest $request, Organization $currentOrganization, Issue $issue, IssueLink $link): RedirectResponse
    {
        $link->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Link removed.')]);

        return back();
    }
}
