<?php

namespace App\Mcp\Tools\Issues;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete an issue permanently, with its comments and links. Needs the manage issues permission. To close an issue instead, set its status to done or cancelled with update-issue.')]
class DeleteIssueTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'delete', $issue);

            $reference = $issue->reference();
            $issue->delete();

            return Response::text(__(':reference deleted.', ['reference' => $reference]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
        ];
    }
}
