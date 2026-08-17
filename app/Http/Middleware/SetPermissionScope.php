<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $request->user()?->current_organization_id;

        if ($organizationId !== null) {
            setPermissionsTeamId($organizationId);
        }

        return $next($request);
    }
}
