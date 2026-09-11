<?php

namespace App\Enums;

enum VoteDirection: string
{
    case Up = 'up';
    case Down = 'down';

    /**
     * The value stored on the vote, so a sum gives the score.
     */
    public function weight(): int
    {
        return $this === self::Up ? 1 : -1;
    }

    public static function fromWeight(int $weight): self
    {
        return $weight > 0 ? self::Up : self::Down;
    }
}
