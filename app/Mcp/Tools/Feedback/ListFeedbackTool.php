<?php

namespace App\Mcp\Tools\Feedback;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Http\Requests\Portal\ProjectBoardRequest;
use App\Http\Resources\FeedbackResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List feedback (feature requests and bug reports) on a project\'s portal board. Without a status it shows the active requests (open, planned, in progress). Sorted by vote score unless sort is "new".')]
class ListFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $project = $this->project($organization, (string) $request->get('project'));
            $this->authorize($request, 'viewFeedbackBoard', $project);

            $board = $this->formRequest(ProjectBoardRequest::class, $request, ['organization' => $organization, 'project' => $project], 'GET', [
                'status' => $request->get('status'),
                'type' => $request->get('type'),
                'sort' => $request->get('sort'),
                'q' => $request->get('search'),
            ]);

            $limit = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']])['limit'] ?? 50;

            $feedback = $project->feedback()
                ->withSummary($user)
                ->onBoard($board->status(), $board->type(), $board->search(), $board->sort())
                ->limit($limit)
                ->get()
                ->map(fn (Feedback $feedback) => FeedbackResource::make($feedback)->withIssue()->resolve());

            return Response::json($feedback->all());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug.')->required(),
            'status' => $schema->string()->enum(array_column(FeedbackStatus::cases(), 'value'))->description('Only feedback in this status. Defaults to the active statuses.'),
            'type' => $schema->string()->enum(array_column(FeedbackType::cases(), 'value'))->description('Only feature requests or only bug reports.'),
            'sort' => $schema->string()->enum(['top', 'new'])->description('top (highest score first, the default) or new (newest first).'),
            'search' => $schema->string()->description('Matches feedback titles.'),
            'limit' => $schema->integer()->description('How many to return, 50 by default and at most 100.'),
        ];
    }
}
