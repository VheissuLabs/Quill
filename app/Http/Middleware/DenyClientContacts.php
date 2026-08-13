<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyClientContacts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization;

        if ($user !== null && $organization !== null && $user->isClientContact($organization)) {
            abort(403);
        }

        return $next($request);
    }
}
