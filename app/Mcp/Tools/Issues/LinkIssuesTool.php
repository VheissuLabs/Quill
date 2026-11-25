<?php

namespace App\Mcp\Tools\Issues;

use App\Enums\IssueLinkType;
use App\Http\Resources\IssueLinkResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Link two issues in the same organization. The link reads from issue_id: type blocks means issue_id blocks related_issue_id, parent means issue_id is the parent of related_issue_id. Set inverse to read it the other way round, so parent with inverse makes issue_id a child of related_issue_id. Returns every link on issue_id.')]
class LinkIssuesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'update', $issue);

            $validated = $request->validate([
                'related_issue_id' => ['required', 'integer'],
                'type' => ['required', Rule::enum(IssueLinkType::class)],
                'inverse' => ['nullable', 'boolean'],
            ]);

            $other = $this->issue($organization, (int) $validated['related_issue_id']);
            $type = IssueLinkType::from($validated['type']);

            ($validated['inverse'] ?? false)
                ? $other->linkTo($issue, $type)
                : $issue->linkTo($other, $type);

            return Response::json(IssueLinkResource::collection($issue->relatedIssues())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('The issue the link reads from.')->required(),
            'related_issue_id' => $schema->integer()->description('The other issue.')->required(),
            'type' => $schema->string()->enum(array_column(IssueLinkType::cases(), 'value'))->description('How issue_id relates to related_issue_id.')->required(),
            'inverse' => $schema->boolean()->description('Read the link from related_issue_id instead, for example "blocked by" or "child of".'),
        ];
    }
}
