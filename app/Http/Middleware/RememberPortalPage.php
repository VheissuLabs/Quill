<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remember the public board or request a visitor is reading, so signing up, logging in or
 * verifying an email address brings them straight back to it instead of to account settings.
 */
class RememberPortalPage
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $request->session()->put('portal.last_url', $request->fullUrl());

            if (! $request->user()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }
        }

        return $next($request);
    }
}
