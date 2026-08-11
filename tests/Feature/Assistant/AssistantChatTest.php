<?php

use App\Ai\Agents\QuillAssistant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

/**
 * Join the `text_delta` frames out of an SSE body, the way the chat window does.
 */
function assistantDeltas(string $stream): string
{
    return collect(explode("\n\n", $stream))
        ->map(fn (string $frame) => trim(Str::after($frame, 'data: ')))
        ->filter(fn (string $payload) => $payload !== '' && $payload !== '[DONE]')
        ->map(fn (string $payload) => json_decode($payload, true))
        ->where('type', 'text_delta')
        ->pluck('delta')
        ->join('');
}

/**
 * Post a message and drain the stream.
 *
 * Draining matters: the conversation is persisted as the generator runs, so a
 * stream nobody reads is a conversation nobody stored.
 */
function sendToAssistant(User $user, string $message): string
{
    $response = test()->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => $message]);

    $response->assertOk();

    return $response->streamedContent();
}

function assistantUser(string $organizationName = 'NotaryDash'): User
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => $organizationName]);

    $organization->members()->attach($user, ['role' => 'owner']);
    $user->switchOrganization($organization);

    return $user->refresh();
}

test('a guest cannot reach the assistant', function () {
    $this->get(route('assistant'))->assertRedirect(route('login'));
    $this->post(route('assistant.messages.store'), ['message' => 'Hi'])
        ->assertRedirect(route('login'));
});

test('the assistant page renders with an empty transcript', function () {
    $this
        ->actingAs(assistantUser())
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assistant')
            ->where('messages', []),
        );
});

test('sending a message streams a reply', function () {
    QuillAssistant::fake(['Ready when you are.']);

    $response = $this
        ->actingAs(assistantUser())
        ->post(route('assistant.messages.store'), ['message' => 'Hello?']);

    $response->assertOk();

    /**
     * The reply arrives as separate `text_delta` frames rather than one string,
     * which is the whole point of streaming — the chat window joins them.
     */
    $stream = $response->streamedContent();

    expect(assistantDeltas($stream))->toBe('Ready when you are.');
    expect($stream)->toContain('data: [DONE]');

    QuillAssistant::assertPrompted('Hello?');
});

test('a message is required and bounded', function () {
    QuillAssistant::fake();

    $user = assistantUser();

    $this->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => ''])
        ->assertSessionHasErrors('message');

    $this->actingAs($user)
        ->post(route('assistant.messages.store'), ['message' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('message');

    QuillAssistant::assertNeverPrompted();
});

test('the transcript survives a reload', function () {
    /**
     * Keyed on the prompt rather than an ordered array: a streamed invocation
     * advances the fake's response index more than once, so positional fakes do
     * not line up with the requests that produced them.
     */
    QuillAssistant::fake(fn (string $prompt) => match ($prompt) {
        'First question' => 'The first answer.',
        'Second question' => 'The second answer.',
        default => 'Unexpected prompt.',
    });

    $user = assistantUser();

    sendToAssistant($user, 'First question');
    sendToAssistant($user, 'Second question');

    $this
        ->actingAs($user)
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 4)
            ->where('messages.0.role', 'user')
            ->where('messages.0.content', 'First question')
            ->where('messages.1.role', 'assistant')
            ->where('messages.3.content', 'The second answer.'),
        );
});

test('a second message continues the same conversation', function () {
    QuillAssistant::fake();

    $user = assistantUser();

    sendToAssistant($user, 'First');
    sendToAssistant($user, 'Second');

    expect(Conversation::count())->toBe(1);
})->note('One running thread per user; a reply must not spawn a fresh conversation.');

test('one user never sees another user transcript', function () {
    QuillAssistant::fake(['A private answer.']);

    $owner = assistantUser();
    $stranger = assistantUser('92 Labs');

    sendToAssistant($owner, 'Something confidential');

    expect($owner->toAssistantMessages())->toHaveCount(2);
    expect($stranger->toAssistantMessages())->toBeEmpty();

    $this
        ->actingAs($stranger)
        ->get(route('assistant'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('messages', []));
})->note('The participant scope is the only thing separating transcripts.');

test('conversations are stored against the uuid participant, not a truncated key', function () {
    QuillAssistant::fake(['Stored.']);

    $user = assistantUser();

    sendToAssistant($user, 'Store this');

    $conversation = Conversation::sole();

    expect($conversation->participant_id)->toBe($user->id);
    expect($conversation->participant_type)->toBe($user->getMorphClass());
})->note('The published migration typed participant_id as a bigint, which would collapse every UUID to 0 on MySQL.');

test('the prompt names the current organization and forbids inventing data', function () {
    $instructions = new QuillAssistant(assistantUser())->instructions();

    expect($instructions)
        ->toContain('NotaryDash')
        ->toContain('never invent')
        ->toContain('Use a tool before answering');
})->note('The tools cannot stop the model answering from imagination; only the prompt can.');

test('the prompt reflects the organization the user switched to', function () {
    $user = assistantUser('NotaryDash');
    $other = Organization::factory()->create(['name' => '92 Labs']);

    $other->members()->attach($user, ['role' => 'member']);
    $user->switchOrganization($other);

    expect(new QuillAssistant($user->refresh())->instructions())
        ->toContain('92 Labs')
        ->not->toContain('NotaryDash');
});
