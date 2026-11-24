<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

test('an mcp client can sign in through oauth and call tools', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, 'Owner');
    $user->switchOrganization($organization);

    $client = $this->postJson('/oauth/register', [
        'client_name' => 'Claude Code',
        'redirect_uris' => ['http://localhost:33418/callback'],
    ])->assertCreated()->json();

    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $authorize = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client['client_id'],
        'redirect_uri' => 'http://localhost:33418/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk()->assertSee('Claude Code');

    preg_match('/name="auth_token" value="([^"]+)"/', $authorize->getContent(), $token);

    $redirect = $this->post('/oauth/authorize', ['state' => 'xyz', 'client_id' => $client['client_id'], 'auth_token' => $token[1]])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

    $accessToken = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client['client_id'],
        'redirect_uri' => 'http://localhost:33418/callback',
        'code' => $query['code'],
        'code_verifier' => $verifier,
    ])->assertOk()->json('access_token');

    auth()->forgetGuards();
    $this->flushSession();

    $this->withToken($accessToken)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list-organizations', 'arguments' => []]])
        ->assertOk()
        ->assertSee($organization->slug);
});

test('the mcp endpoint asks unauthenticated clients to sign in with oauth', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});
