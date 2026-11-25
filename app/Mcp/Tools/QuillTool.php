<?php

namespace App\Mcp\Tools;

use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class QuillTool extends Tool
{
    public function name(): string
    {
        return Str::kebab(Str::beforeLast(class_basename($this), 'Tool'));
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException(__('Sign in to Quill to use this tool.'));
        }

        return $user;
    }

    protected function organization(Request $request): Organization
    {
        $user = $this->user($request);
        $slug = $request->get('organization');

        $organization = filled($slug)
            ? Organization::query()->where('slug', $slug)->first()
            : $user->currentOrganization;

        if (! $organization instanceof Organization || ! $user->belongsToOrganization($organization)) {
            throw new AuthorizationException(__('You are not a member of that organization. Use list-organizations to see yours.'));
        }

        setPermissionsTeamId($organization->id);

        return $organization;
    }

    protected function today(Request $request): string
    {
        return CarbonImmutable::now($this->user($request)->timezone ?? config('app.timezone'))->toDateString();
    }

    protected function project(Organization $organization, int|string $identifier): Project
    {
        return $organization->projects()->identifiedBy($identifier)->firstOrFail();
    }

    protected function issue(Organization $organization, int|string $id): Issue
    {
        return Issue::query()
            ->whereIn('project_id', $organization->projects()->select('projects.id'))
            ->findOrFail((int) $id);
    }

    protected function authorize(Request $request, string $ability, mixed $arguments): void
    {
        Gate::forUser($this->user($request))->authorize($ability, $arguments);
    }

    /**
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $formRequest
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>|null  $input
     * @return TRequest
     */
    protected function formRequest(string $formRequest, Request $request, array $parameters, string $method = 'POST', ?array $input = null): FormRequest
    {
        $input ??= collect($request->all())->except('organization')->all();

        $route = new Route($method, '/', []);
        $route->parameters = $parameters;

        $httpRequest = $formRequest::create('/', $method, $input);
        $httpRequest->setContainer(app())->setRedirector(app('redirect'));
        $httpRequest->setUserResolver(fn () => $this->user($request));
        $httpRequest->setRouteResolver(fn () => $route);
        $httpRequest->validateResolved();

        return $httpRequest;
    }

    /** @param  callable(): Response  $handler */
    protected function attempt(callable $handler): Response
    {
        try {
            return $handler();
        } catch (DomainException $exception) {
            return Response::error($exception->getMessage());
        } catch (ModelNotFoundException) {
            return Response::error(__('Nothing matched that id in this organization.'));
        } catch (HttpException $exception) {
            return Response::error($exception->getMessage() ?: __('That is not allowed here.'));
        }
    }

    protected function organizationArgument(JsonSchema $schema): Type
    {
        return $schema->string()->description('Organization slug. Defaults to your current organization; list-organizations shows the slugs.');
    }
}
