<?php

namespace App\Mcp\Tools\Issues;

use App\Enums\IssueLinkType;
use App\Http\Requests\Issues\UpdateIssueLinkRequest;
use App\Http\Resources\IssueLinkResource;
use App\Mcp\Tools\QuillTool;
use App\Models\IssueLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change the type or direction of a link between two issues, as read from issue_id. Link ids come from show-issue. Returns every link on issue_id.')]
class UpdateIssueLinkTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'update', $issue);

            $link = IssueLink::query()->findOrFail((int) $request->get('link_id'));

            $validated = $this->formRequest(UpdateIssueLinkRequest::class, $request, ['current_organization' => $organization, 'issue' => $issue, 'link' => $link], 'PATCH', [
                'type' => $request->get('type'),
                'inverse' => $request->get('inverse', false),
            ]);

            $issue->recastLink($link, IssueLinkType::from($validated->validated('type')), $validated->boolean('inverse'));

            return Response::json(IssueLinkResource::collection($issue->relatedIssues())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('One of the two linked issues; the link is read from its side.')->required(),
            'link_id' => $schema->integer()->description('Link id, from show-issue.')->required(),
            'type' => $schema->string()->enum(array_column(IssueLinkType::cases(), 'value'))->description('How issue_id relates to the other issue.')->required(),
            'inverse' => $schema->boolean()->description('Read the link from the other issue instead, for example "blocked by" or "child of". Defaults to false.'),
        ];
    }
}
