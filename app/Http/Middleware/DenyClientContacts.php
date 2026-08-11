<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyClientContacts
{
    /**
     * Keep client contacts out of the staff-facing assistant.
     *
     * Its tools answer for the whole organization, so a contact for one client
     * could read another client's people. The design gives contacts their own
     * narrower grant later; until then they have no business here.
     */
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
