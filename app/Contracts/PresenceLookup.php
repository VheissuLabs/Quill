<?php

namespace App\Contracts;

use App\Models\User;

interface PresenceLookup
{
    /**
     * Whether the user currently has a live WebSocket connection.
     *
     * Implementations must fail open: if the answer cannot be determined, report
     * the user as offline so that fallback channels such as email still fire.
     */
    public function isOnline(User $user): bool;
}
