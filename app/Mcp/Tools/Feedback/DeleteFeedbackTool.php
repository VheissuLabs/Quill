<?php

namespace App\Mcp\Tools\Feedback;

use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a feedback request with its votes and comments. Authors can delete their own; feedback managers can delete any.')]
class DeleteFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'delete', $feedback);

            $feedback->delete();

            return Response::text(__('Deleted ":title".', ['title' => $feedback->title]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
        ];
    }
}
