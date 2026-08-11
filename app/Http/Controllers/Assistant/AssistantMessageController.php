<?php

namespace App\Http\Controllers\Assistant;

use App\Ai\Agents\QuillAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AssistantMessageController extends Controller
{
    /**
     * Stream the assistant's reply to a message as server-sent events.
     *
     * Returned as SSE rather than an Inertia visit: an Inertia response replaces
     * page props in one shot, which cannot express token-by-token output.
     */
    public function store(Request $request): StreamableAgentResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        /**
         * `continueLastConversation` resumes the user's most recent thread, or
         * leaves the id null when there is none — in which case prompting starts
         * a new conversation. One running thread per user, which is all the chat
         * window offers today.
         */
        return QuillAssistant::make(user: $user)
            ->continueLastConversation($user)
            ->stream($validated['message']);
    }
}
