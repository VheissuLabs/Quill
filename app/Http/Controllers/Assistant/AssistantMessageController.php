<?php

namespace App\Http\Controllers\Assistant;

use App\Ai\Agents\QuillAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AssistantMessageController extends Controller
{
    public function store(Request $request): StreamableAgentResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        return QuillAssistant::make(user: $user)
            ->continueLastConversation($user)
            ->stream($validated['message']);
    }
}
