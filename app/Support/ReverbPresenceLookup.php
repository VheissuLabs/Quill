<?php

namespace App\Support;

use App\Contracts\PresenceLookup;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverbPresenceLookup implements PresenceLookup
{
    public function isOnline(User $user): bool
    {
        try {
            $broadcaster = Broadcast::connection('reverb');

            if (! $broadcaster instanceof PusherBroadcaster) {
                return false;
            }

            $channel = $broadcaster->getPusher()->getChannelInfo(
                'presence-users.'.$user->id,
                ['info' => 'user_count'],
            );

            return (int) ($channel->user_count ?? 0) > 0;
        } catch (Throwable $e) {
            Log::warning('Presence lookup failed; assuming the user is offline.', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
