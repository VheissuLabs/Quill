<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectGuestsFromPrivateProject
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if (! $request->user() && $project instanceof Project && ! $project->isPublic()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
