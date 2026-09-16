<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentOrganization
{
    protected function redirectPathForCurrentOrganization(Request $request, string $redirect): string
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $organization = $user->currentOrganization ?? $user->fallbackOrganization();

        if (! $organization) {
            return $request->session()->get('portal.last_url', route('organizations.index', absolute: false));
        }

        URL::defaults(['current_organization' => $organization->slug]);

        return "/{$organization->slug}{$redirect}";
    }
}
