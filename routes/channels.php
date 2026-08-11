<?php

use Illuminate\Support\Facades\Broadcast;

// Returning false is deliberate: until Task 3 writes real checks, denying
// everything is the safe placeholder. Never ship the framework's stub version.
Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return false;
});
