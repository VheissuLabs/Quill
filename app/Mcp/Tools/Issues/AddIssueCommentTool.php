<?php

namespace App\Mcp\Tools\Issues;

use App\Actions\Comments\PostIssueComment;
use App\Http\Requests\Comments\StoreIssueCommentRequest;
use App\Http\Resources\CommentResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Comment on an issue, or reply to a comment with parent_id. Lines starting with a slash command run instead of being posted, such as /status done, /assign me, /sprint Sprint 4, /points 3, /priority high, /blocks 42 or /unlink 42. Mention people by user id in mentions to notify them.')]
class AddIssueCommentTool extends QuillTool
{
    public function handle(Request $request, PostIssueComment $postComment): Response
    {
        return $this->attempt(function () use ($request, $postComment) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'comment', $issue);

            $validated = $this->formRequest(StoreIssueCommentRequest::class, $request, ['current_organization' => $organization, 'issue' => $issue], input: [
                'body' => $request->get('body'),
                'parent_id' => $request->get('parent_id'),
                'mentions' => $request->get('mentions', []),
            ]);

            $parent = $validated->filled('parent_id')
                ? $issue->comments()->whereKey($validated->integer('parent_id'))->firstOrFail()
                : null;

            $result = $postComment->handle($issue, $user, $validated->validated('body'), $validated->validated('mentions', []), $parent);

            return Response::json([
                'comment' => $result['comment'] ? CommentResource::make($result['comment']->load(['author', 'mentions']), $user)->resolve() : null,
                'messages' => $result['messages'],
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
            'body' => $schema->string()->description('The comment, up to 5000 characters. May contain slash commands, one per line.')->required(),
            'parent_id' => $schema->integer()->description('Comment id to reply to, from show-issue.'),
            'mentions' => $schema->array()->items($schema->integer())->description('User ids of organization members to mention.'),
        ];
    }
}
