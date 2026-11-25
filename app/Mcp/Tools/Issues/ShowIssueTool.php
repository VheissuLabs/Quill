<?php

namespace App\Mcp\Tools\Issues;

use App\Http\Resources\CommentResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\IssueDetailResource;
use App\Http\Resources\IssueLinkResource;
use App\Http\Resources\LinkedTicketResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show one issue in full: its description, board column, sprint, assignee, linked issues (with link ids for update-issue-link and delete-issue-link), comment threads (with comment ids), the feedback it came from and any support tickets waiting on it.')]
class ShowIssueTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'view', $issue);

            $issue->load(['project.organization', 'team.columns', 'sprint', 'boardColumn.owner', 'assignee', 'creator', 'feedback']);

            $comments = $issue->comments()->threadsVisibleTo(true)->limit(50)->get();

            return Response::json([
                'issue' => [
                    ...IssueDetailResource::make($issue, $user)->resolve(),
                    'sprintName' => $issue->sprint?->name,
                ],
                'links' => IssueLinkResource::collection($issue->relatedIssues())->resolve(),
                'comments' => $comments->map(fn (Comment $comment) => CommentResource::make($comment, $user)->resolve())->all(),
                'feedback' => $issue->feedback ? FeedbackResource::make($issue->feedback)->resolve() : null,
                'tickets' => LinkedTicketResource::forIssue($issue, $user),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id, as list-issues returns it.')->required(),
        ];
    }
}
