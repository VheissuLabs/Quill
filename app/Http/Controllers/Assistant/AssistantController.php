<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Assistant', [
            'messages' => $request->user()->toAssistantMessages(),
        ]);
    }
}
