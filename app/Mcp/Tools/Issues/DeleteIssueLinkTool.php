<?php

namespace App\Mcp\Tools\Issues;

use App\Http\Requests\Issues\IssueLinkRequest;
use App\Mcp\Tools\QuillTool;
use App\Models\IssueLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove a link between two issues. Link ids come from show-issue.')]
class DeleteIssueLinkTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'update', $issue);

            $link = IssueLink::query()->findOrFail((int) $request->get('link_id'));

            $this->formRequest(IssueLinkRequest::class, $request, ['current_organization' => $organization, 'issue' => $issue, 'link' => $link], 'DELETE', []);

            $link->delete();

            return Response::text(__('Link removed.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('One of the two linked issues.')->required(),
            'link_id' => $schema->integer()->description('Link id, from show-issue.')->required(),
        ];
    }
}
