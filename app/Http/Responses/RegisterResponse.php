<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentOrganization;
use App\Http\Responses\Concerns\StoresTheBrowserTimezone;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsToCurrentOrganization, StoresTheBrowserTimezone;

    public function toResponse($request): Response
    {
        $this->storeSubmittedTimezone($request);

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : redirect()->intended($this->redirectPathForCurrentOrganization($request, Fortify::redirects('register')));
    }
}
