<?php

namespace App\Mcp\Tools\Issues;

use App\Events\IssuesChanged;
use App\Http\Requests\Issues\UpdateIssueOrderRequest;
use App\Mcp\Tools\QuillTool;
use App\Models\Issue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Set the order of issues on the backlog. Pass issue ids top to bottom; each takes the position of its place in the list.')]
class ReorderIssuesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Issue::class, $organization]);

            $validated = $this->formRequest(UpdateIssueOrderRequest::class, $request, ['current_organization' => $organization], 'PUT');

            $ids = array_map('intval', array_values($validated->validated('issue_ids')));

            Issue::reorder($ids);

            broadcast(new IssuesChanged($organization->getKey(), $ids))->toOthers();

            return Response::text(__('Issues reordered.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_ids' => $schema->array()->items($schema->integer())->description('Issue ids in their new order, top first.')->required(),
        ];
    }
}
