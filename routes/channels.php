<?php

use App\Events\OrganizationBroadcast;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel(OrganizationBroadcast::CHANNEL_PATTERN, function (User $user, int $organization) {
    $organization = Organization::find($organization);

    return $organization !== null && $user->belongsToOrganization($organization);
});
