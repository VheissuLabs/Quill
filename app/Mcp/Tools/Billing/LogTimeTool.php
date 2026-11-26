<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\LogTime;
use App\Http\Resources\LoggedTimeResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Log hours worked on a Quill issue on a given day (default today). The time is always recorded, and it rolls onto that issue\'s line on the client\'s draft invoice for the billing period containing the day, at the project\'s hourly rate, so the draft total stays current. If that period\'s invoice was already sent, the time is recorded but not billed. For work tracked outside Quill (a client\'s Jira, GitHub, Monday ticket, or meetings), use track-time.')]
class LogTimeTool extends QuillTool
{
    public function handle(Request $request, LogTime $logTime): Response
    {
        return $this->attempt(function () use ($request, $logTime) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'view', $issue);
            $this->authorize($request, 'create', [Invoice::class, $organization]);

            $validated = $request->validate([
                'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
                'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->today($request)],
            ]);

            $client = $issue->project->client
                ?? throw new DomainException(__(':project has no client, so its time has no invoice to go on.', ['project' => $issue->project->name]));

            $entry = $logTime->handle(
                client: $client,
                workedOn: CarbonImmutable::parse($validated['date'] ?? $this->today($request)),
                hours: (float) $validated['hours'],
                user: $user,
                issue: $issue,
            );

            return Response::json(LoggedTimeResource::make($entry)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
            'hours' => $schema->number()->description('Hours worked, e.g. 1.5 for an hour and a half.')->required(),
            'date' => $schema->string()->format('date')->description('Day the work was done, YYYY-MM-DD. Defaults to today in your timezone.'),
        ];
    }
}
