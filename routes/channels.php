<?php

use App\Events\OrganizationBroadcast;
use App\Models\Feedback;
use App\Models\Issue;
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

Broadcast::channel('feedback.{feedback}', function (User $user, int $feedback) {
    $feedback = Feedback::with('project')->find($feedback);

    return $feedback !== null && $user->can('view', $feedback->project)
        ? ['id' => $user->getKey(), 'name' => $user->name]
        : null;
});

Broadcast::channel('issues.{issue}', function (User $user, int $issue) {
    $issue = Issue::with('project')->find($issue);

    return $issue !== null && $user->can('view', $issue->project)
        ? ['id' => $user->getKey(), 'name' => $user->name]
        : null;
});
