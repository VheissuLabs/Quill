---
paths:
  - 'app/Ai/**'
---

# Ai

## Testing streamed agents: drain the stream, key fakes on the prompt
The conversation is persisted by the `RememberConversation` middleware as the stream generator runs. A test that posts to a streaming route and only calls `assertOk()` persists nothing — call `->streamedContent()` to drain it, or the transcript assertions will silently see zero messages.

Do not use positional array fakes (`Agent::fake(['a', 'b'])`) with streamed invocations: a single stream advances the fake's response index more than once, so the second request falls through to the default fake response. Key the fake on the prompt instead: `Agent::fake(fn (string $prompt) => match ($prompt) { ... })`. Note the closure receives the prompt as a **string**, not an `AgentPrompt`.

Replies arrive as many `text_delta` frames, so asserting the SSE body `toContain('the whole sentence')` fails. Join the deltas first.
