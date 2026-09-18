<?php

namespace App\Http\Controllers\Issues;

use App\Actions\Comments\IssueCommentCommands;
use App\Actions\Comments\NotifyAboutComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreIssueCommentRequest;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreIssueCommentRequest $request, Organization $currentOrganization, Issue $issue, IssueCommentCommands $commands, NotifyAboutComment $notify): RedirectResponse
    {
        $parent = $request->filled('parent_id')
            ? $issue->comments()->whereKey($request->integer('parent_id'))->firstOrFail()
            : null;

        $result = $commands->handle($issue, $request->user(), $request->validated('body'));

        if ($result['body'] !== '') {
            $comment = DB::transaction(function () use ($request, $issue, $parent, $result) {
                $comment = $issue->comments()->create([
                    'body' => $result['body'],
                    'is_internal' => false,
                    'parent_id' => $parent->parent_id ?? $parent?->id,
                    'user_id' => $request->user()->id,
                ]);

                $comment->mentions()->sync($request->validated('mentions', []));

                return $comment;
            });

            $notify->handle($comment, $request->user(), $parent);
        }

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
