<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Resources\ClientResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the organization\'s clients by name, with their slug and project count. Pass deleted=true to list deleted clients instead, which restore-client can bring back.')]
class ListClientsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Client::class, $organization]);

            $validated = $request->validate([
                'deleted' => ['nullable', 'boolean'],
            ]);

            $clients = $organization->clients()
                ->when($validated['deleted'] ?? false, fn ($query) => $query->onlyTrashed())
                ->withCount('projects')
                ->orderBy('name')
                ->get();

            return Response::json(ClientResource::collection($clients)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'deleted' => $schema->boolean()->description('List deleted clients instead of active ones.'),
        ];
    }
}
