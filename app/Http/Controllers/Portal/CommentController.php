<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\PostFeedbackComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Organization $organization, Project $project, Feedback $feedback, PostFeedbackComment $postComment): RedirectResponse
    {
        $result = $postComment->handle(
            $feedback,
            $request->user(),
            $request->validated('body'),
            $request->boolean('internal'),
            $request->filled('parent_id') ? $request->integer('parent_id') : null,
            $request->validated('mentions', []),
        );

        if ($result['messages'] !== []) {
            Inertia::flash('toast', ['type' => 'success', 'message' => implode(' ', $result['messages'])]);
        }

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feedback $feedback, Comment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment deleted.')]);

        return back();
    }
}
