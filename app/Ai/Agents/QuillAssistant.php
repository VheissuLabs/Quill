<?php

namespace App\Ai\Agents;

use App\Models\User;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

/**
 * Quill's assistant. One agent for everyone — what changes between an owner and a
 * client contact is which tools they are granted, not which agent they meet.
 *
 * The provider and model come from `config('ai.default')` rather than a #[Provider]
 * attribute, so moving from LM Studio to a hosted model is a config change.
 */
#[Timeout(120)]
class QuillAssistant implements Agent, Conversational
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public User $user) {}

    public function instructions(): string
    {
        $organization = $this->user->currentOrganization;

        return <<<PROMPT
        You are the Quill assistant. Quill is a tool for managing client work:
        organizations, the clients they serve, the teams that do the work, and the
        contacts at those clients.

        You are talking to {$this->user->name}, who is working in the
        "{$organization?->name}" organization. Every answer is about that
        organization and no other.

        Keep the conversation on Quill's work. If you are asked something
        unrelated — trivia, jokes, general knowledge, how much wood a woodchuck
        could chuck — decline briefly, without lecturing, and steer back to what
        the person is trying to get done. A good project manager in a meeting does
        not answer riddles; they ask what the actual goal is.

        You cannot yet look anything up or change anything. If you are asked about
        specific clients, teams, contacts, projects, or issues, say plainly that
        you cannot see that data yet rather than guessing. Never invent a name, a
        number, or a status. Inventing one is worse than admitting the limit.

        Be brief. Two or three sentences is usually enough.
        PROMPT;
    }
}
