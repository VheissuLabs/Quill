<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentOrganization
{
    /**
     * Get the path to redirect to within the user's current organization, or to the
     * organizations settings page when the user does not belong to any organization yet.
     */
    protected function redirectPathForCurrentOrganization(Request $request, string $redirect): string
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $organization = $user->currentOrganization ?? $user->fallbackOrganization();

        if (! $organization) {
            return route('organizations.index', absolute: false);
        }

        URL::defaults(['current_organization' => $organization->slug]);

        return "/{$organization->slug}{$redirect}";
    }
}
