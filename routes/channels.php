<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Compared as strings. Laravel's published stub casts both sides with `(int)`,
 * which evaluates every UUID to 0 and authorizes every user for every other
 * user's channel.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, string $id): bool {
    return hash_equals($user->id, $id);
});

/**
 * A per-user presence channel whose only purpose is to answer "is this person
 * connected right now?" for email suppression. Nothing subscribes to it until
 * deliverable 6; authorizing it here keeps the lookup and its channel together.
 */
Broadcast::channel('users.{id}', function (User $user, string $id): ?array {
    if (! hash_equals($user->id, $id)) {
        return null;
    }

    return ['id' => $user->id];
});

/**
 * Presence of members within one organization. A Client-role contact belongs to
 * the organization and is admitted deliberately — they are a member.
 */
Broadcast::channel('organizations.{organizationId}', function (User $user, string $organizationId): ?array {
    $organization = Organization::find($organizationId);

    if ($organization === null || ! $user->belongsToOrganization($organization)) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
