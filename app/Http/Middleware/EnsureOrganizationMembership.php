<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$user, $organization] = [$request->user(), $this->organization($request)];

        abort_if(! $user || ! $organization || ! $user->belongsToOrganization($organization), 403);

        setPermissionsTeamId($organization->id);

        if ($request->route('current_organization') && ! $user->isCurrentOrganization($organization)) {
            $user->switchOrganization($organization);
        }

        return $next($request);
    }

    protected function organization(Request $request): ?Organization
    {
        $organization = $request->route('current_organization') ?? $request->route('organization');

        if (is_string($organization)) {
            $organization = Organization::where('slug', $organization)->first();
        }

        return $organization;
    }
}
