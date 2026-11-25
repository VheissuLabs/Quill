<?php

namespace App\Mcp\Tools\Billing;

use App\Mcp\Tools\QuillTool;
use App\Models\BillingSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the work types invoice lines can be tagged with, such as Development or Design. Inactive ones are kept for old lines but not offered for new ones.')]
class ListWorkTypesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [BillingSetting::class, $organization]);

            return Response::json($organization->workTypes()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'color', 'is_active'])
                ->toArray());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
