<?php

namespace App\Http\Controllers\Assistant;

use App\Ai\Agents\QuillAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\SendAssistantMessageRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AssistantController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('assistant/Show', [
            'messages' => $request->user()->toAssistantMessages(),
        ]);
    }

    /**
     * Streams over SSE rather than returning an Inertia response: an Inertia visit
     * replaces page props, which is the wrong shape for token-by-token output.
     */
    public function store(SendAssistantMessageRequest $request): StreamableAgentResponse
    {
        $user = $request->user();

        return QuillAssistant::make(user: $user)
            ->continueLastConversation($user)
            ->stream($request->validated('message'));
    }
}
