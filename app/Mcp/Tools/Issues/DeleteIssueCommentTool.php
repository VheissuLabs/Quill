<?php

namespace App\Mcp\Tools\Issues;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a comment on an issue. You can delete your own comments; deleting anyone else\'s needs the manage issues permission. Comment ids come from show-issue.')]
class DeleteIssueCommentTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $comment = $issue->comments()->findOrFail((int) $request->get('comment_id'));
            $this->authorize($request, 'delete', $comment);

            $comment->delete();

            return Response::text(__('Comment deleted.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
            'comment_id' => $schema->integer()->description('Comment id, from show-issue.')->required(),
        ];
    }
}
