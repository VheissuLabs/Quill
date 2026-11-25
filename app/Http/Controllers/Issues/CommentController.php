<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Comments\PostIssueComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreIssueCommentRequest;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreIssueCommentRequest $request, Organization $currentOrganization, Issue $issue, PostIssueComment $postComment): RedirectResponse
    {
        $parent = $request->filled('parent_id')
            ? $issue->comments()->whereKey($request->integer('parent_id'))->firstOrFail()
            : null;

        $result = $postComment->handle($issue, $request->user(), $request->validated('body'), $request->validated('mentions', []), $parent);

        if ($result['messages'] !== []) {
            Inertia::flash('toast', ['type' => 'success', 'message' => implode(' ', $result['messages'])]);
        }

        return back();
    }

    public function destroy(Organization $currentOrganization, Issue $issue, Comment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment deleted.')]);

        return back();
    }
}
