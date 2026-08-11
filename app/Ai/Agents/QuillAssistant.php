<?php

namespace App\Ai\Agents;

use App\Ai\Tools\DescribeOrganization;
use App\Ai\Tools\ListClients;
use App\Ai\Tools\ListContacts;
use App\Ai\Tools\ListTeams;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Quill's assistant. One agent for everyone — what changes between an owner and a
 * client contact is which tools they are granted, not which agent they meet.
 *
 * The provider and model come from `config('ai.default')` rather than a #[Provider]
 * attribute, so moving from LM Studio to a hosted model is a config change.
 */
#[Timeout(120)]
#[MaxSteps(6)]
class QuillAssistant implements Agent, Conversational, HasTools
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
        organization and no other. Your tools only ever return that
        organization's data, so if you are asked about a different organization,
        say you can only see this one.

        Use a tool before answering any question about clients, teams, contacts,
        or the organization. Never answer those from memory, and never invent a
        name, an email address, a count, or a status — a made-up answer is worse
        than admitting you do not know. If a tool returns nothing, say so plainly.

        You cannot create or change anything yet. If you are asked to, say that
        you can only read information for now.

        Projects and issues do not exist in Quill yet. If you are asked about
        them, say they are not available rather than guessing.

        Keep the conversation on Quill's work. If you are asked something
        unrelated — trivia, jokes, general knowledge, how much wood a woodchuck
        could chuck — decline briefly, without lecturing, and steer back to what
        the person is trying to get done. A good project manager in a meeting does
        not answer riddles; they ask what the actual goal is.

        Be brief. Two or three sentences is usually enough. When you list things,
        use a short list rather than a paragraph.
        PROMPT;
    }

    /** @return iterable<Tool> */
    public function tools(): iterable
    {
        return [
            new DescribeOrganization($this->user),
            new ListClients($this->user),
            new ListTeams($this->user),
            new ListContacts($this->user),
        ];
    }
}
