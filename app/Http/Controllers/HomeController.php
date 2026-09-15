<?php

namespace App\Http\Controllers;

use App\Http\Responses\Concerns\RedirectsToCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class HomeController extends Controller
{
    use RedirectsToCurrentOrganization;

    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return to_route('login');
        }

        return redirect($this->redirectPathForCurrentOrganization($request, Fortify::redirects('login')));
    }
}
