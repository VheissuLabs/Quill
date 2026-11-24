<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\LogTime;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Log hours worked on an issue. The hours are added to that issue\'s line on its client\'s open draft invoice (the current billing period\'s invoice, or a new draft), at the project\'s hourly rate. Logging again adds to the same line.')]
class LogTimeTool extends QuillTool
{
    public function handle(Request $request, LogTime $logTime): Response
    {
        return $this->attempt(function () use ($request, $logTime) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'view', $issue);
            $this->authorize($request, 'create', [Invoice::class, $organization]);

            $validated = $request->validate([
                'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            ]);

            $line = $logTime->handle($issue, (float) $validated['hours']);

            return Response::json([
                'logged' => __(':hours h on :issue', ['hours' => round((float) $validated['hours'], 2), 'issue' => $issue->reference()]),
                'lineId' => $line->id,
                'invoice' => InvoiceResource::make($line->invoice->refresh()->load(['client', 'organization', 'lines.issue.project', 'payments']))->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
            'hours' => $schema->number()->description('Hours worked, e.g. 1.5 for an hour and a half.')->required(),
        ];
    }
}
