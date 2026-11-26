<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\LogTime;
use App\Http\Resources\LoggedTimeResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record time worked for a client on a given day (default today), for work that is not a Quill issue: a ticket in the client\'s own tracker (Jira, GitHub, Monday, YouTrack...) or recurring work such as meetings. The time is always recorded, and it rolls onto the client\'s draft invoice for the billing period containing the day, so the draft total stays current. Entries with the same reference_url, or with the same description and work type when there is no link, add to one invoice line. If that period\'s invoice was already sent, the time is recorded but not billed. For Quill issues use log-time.')]
class TrackTimeTool extends QuillTool
{
    public function handle(Request $request, LogTime $logTime): Response
    {
        return $this->attempt(function () use ($request, $logTime) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'view', $client);
            $this->authorize($request, 'create', [Invoice::class, $organization]);

            $validated = $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
                'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->today($request)],
                'reference_url' => ['nullable', 'url', 'max:2048'],
                'work_type_id' => ['nullable', 'integer', Rule::exists('work_types', 'id')->where('organization_id', $organization->id)],
            ]);

            $entry = $logTime->handle(
                client: $client,
                workedOn: CarbonImmutable::parse($validated['date'] ?? $this->today($request)),
                hours: (float) $validated['hours'],
                user: $user,
                description: $validated['description'],
                referenceUrl: $validated['reference_url'] ?? null,
                workTypeId: isset($validated['work_type_id']) ? (int) $validated['work_type_id'] : null,
            );

            return Response::json(LoggedTimeResource::make($entry)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id; list-clients shows them.')->required(),
            'description' => $schema->string()->description('What the work was, e.g. the ticket title or "Daily Stand Up". Becomes the invoice line description.')->required(),
            'hours' => $schema->number()->description('Hours worked, e.g. 0.33 for 20 minutes.')->required(),
            'date' => $schema->string()->format('date')->description('Day the work was done, YYYY-MM-DD. Defaults to today in your timezone.'),
            'reference_url' => $schema->string()->format('uri')->description('Link to the client\'s ticket or the pull request, so the client can follow it from the invoice.'),
            'work_type_id' => $schema->integer()->description('Work type id; list-work-types shows them.'),
        ];
    }
}
