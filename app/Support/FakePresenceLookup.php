<?php

namespace App\Support;

use App\Contracts\PresenceLookup;
use App\Models\User;

class FakePresenceLookup implements PresenceLookup
{
    public function __construct(protected bool $online = false) {}

    public function isOnline(User $user): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }
}
