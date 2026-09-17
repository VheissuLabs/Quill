<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Comments\FeatureCommentCommands;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Organization $organization, Project $project, Feature $feature, FeatureCommentCommands $commands): RedirectResponse
    {
        $parent = $request->filled('parent_id')
            ? $feature->comments()->whereKey($request->integer('parent_id'))->firstOrFail()
            : null;

        $result = $commands->handle($feature, $request->user(), $request->validated('body'));

        $internal = $request->boolean('internal') || $result['internal'] || ($parent->is_internal ?? false);

        if ($result['body'] !== '') {
            $comment = DB::transaction(function () use ($request, $feature, $parent, $internal, $result) {
                $comment = $feature->comments()->create([
                    'body' => $result['body'],
                    'is_internal' => $internal,
                    'parent_id' => $parent->parent_id ?? $parent?->id,
                    'user_id' => $request->user()->id,
                ]);

                $comment->mentions()->sync($request->validated('mentions', []));

                return $comment;
            });

            $comment->mentions
                ->reject(fn (User $user) => $user->is($request->user()))
                ->each(fn (User $user) => $user->notify(new MentionedInComment($comment)));
        }

        if ($result['messages'] !== []) {
            Inertia::flash('toast', ['type' => 'success', 'message' => implode(' ', $result['messages'])]);
        }

        return back();
    }

    public function destroy(Organization $organization, Project $project, Feature $feature, Comment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment deleted.')]);

        return back();
    }
}
