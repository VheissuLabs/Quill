<?php

namespace App\Mcp\Tools\Organizations;

use App\Actions\Palette\SearchOrganization;
use App\Http\Requests\Palette\PaletteSearchRequest;
use App\Http\Resources\PaletteResultResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search an organization by name the way the web app\'s command palette does: projects, issues (by title or number), feedback, sprints, teams and clients you can see, up to five of each, with links. Needs at least two characters.')]
class SearchTool extends QuillTool
{
    public function handle(Request $request, SearchOrganization $search): Response
    {
        return $this->attempt(function () use ($request, $search) {
            $organization = $this->organization($request);

            $validated = $this->formRequest(PaletteSearchRequest::class, $request, ['current_organization' => $organization], 'GET', ['q' => $request->get('query')]);

            return Response::json(PaletteResultResource::collection($search->handle($organization, $this->user($request), $validated->term()))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'query' => $schema->string()->description('Text to search for, at least two characters.')->required(),
        ];
    }
}
