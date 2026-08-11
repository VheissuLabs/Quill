<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class JoinFromInvitation
{
    public function __construct(protected AcceptInvitation $acceptInvitation) {}

    /**
     * Create an account for someone invited who has none, and accept.
     *
     * They get no organization of their own and no personal team: they are
     * joining someone else's organization, and a client contact holds no team
     * membership by design.
     *
     * The email is marked verified because accepting from the invitation sent to
     * that address is the proof a verification email would have asked for.
     */
    public function handle(OrganizationInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $this->acceptInvitation->handle($user, $invitation);

            return $user;
        });
    }
}
