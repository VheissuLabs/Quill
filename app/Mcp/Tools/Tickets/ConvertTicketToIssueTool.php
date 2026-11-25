<?php

namespace App\Mcp\Tools\Tickets;

use App\Actions\Tickets\ConvertTicketToIssue;
use App\Http\Requests\Tickets\ConvertTicketRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Track a support ticket as an issue. Give project_id to create a backlog issue from the ticket\'s subject and first message in one of the ticket client\'s projects, or issue_id to link an existing issue. The ticket must be assigned to a client first, and you become the issue\'s reporter. Returns the issue.')]
class ConvertTicketToIssueTool extends QuillTool
{
    public function handle(Request $request, ConvertTicketToIssue $convert): Response
    {
        return $this->attempt(function () use ($request, $convert) {
            $organization = $this->organization($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $this->authorize($request, 'convert', $ticket);

            $validated = $this->formRequest(ConvertTicketRequest::class, $request, ['current_organization' => $organization, 'ticket' => $ticket],
                input: collect($request->all())->only(['project_id', 'issue_id'])->all());

            $issue = $convert->handle(
                $ticket,
                $this->user($request),
                $validated->filled('issue_id') ? $validated->integer('issue_id') : null,
                $validated->filled('project_id') ? $validated->integer('project_id') : null,
            );

            return Response::json(IssueResource::make($issue->load(['project', 'team.columns', 'boardColumn', 'assignee']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
            'project_id' => $schema->integer()->description('Create a new issue in this project. It must belong to the ticket\'s client.'),
            'issue_id' => $schema->integer()->description('Link this existing issue instead.'),
        ];
    }
}
