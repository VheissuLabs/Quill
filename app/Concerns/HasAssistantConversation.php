<?php

namespace App\Concerns;

use App\Data\AssistantMessage;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

trait HasAssistantConversation
{
    /**
     * The most recent assistant conversation belonging to this user, if any.
     *
     * Scoped by participant so one user can never load another's transcript. The
     * assistant will eventually hold data the asker is not entitled to see, so
     * this scope is load-bearing rather than tidy.
     */
    public function latestAssistantConversation(): ?Conversation
    {
        return Conversation::query()
            ->where('participant_type', $this->getMorphClass())
            ->where('participant_id', $this->getKey())
            ->latest('updated_at')
            ->first();
    }

    /**
     * The current transcript, oldest first, ready for the chat window.
     *
     * @return Collection<int, AssistantMessage>
     */
    public function toAssistantMessages(): Collection
    {
        $conversation = $this->latestAssistantConversation();

        if ($conversation === null) {
            return collect();
        }

        return $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('content', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ConversationMessage $message): AssistantMessage => new AssistantMessage(
                id: (string) $message->getKey(),
                role: strval($message->getAttribute('role')),
                content: strval($message->getAttribute('content')),
            ))
            ->values();
    }
}
