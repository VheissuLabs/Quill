<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMembershipController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])
            ->middleware('can:update,team')
            ->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware('can:delete,team')
            ->name('teams.destroy');

        Route::put('settings/teams/{team}/membership', [TeamMembershipController::class, 'update'])
            ->middleware('can:view,team')
            ->name('teams.membership.update');

        Route::delete('settings/teams/{team}/membership', [TeamMembershipController::class, 'destroy'])
            ->middleware('can:leave,team')
            ->name('teams.membership.destroy');

        Route::delete('settings/teams/{team}/members/{user}', [TeamMembershipController::class, 'destroy'])
            ->middleware('can:removeMember,team,user')
            ->name('teams.members.destroy');

        Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])
            ->middleware('can:inviteMember,team')
            ->name('teams.invitations.store');
        Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])
            ->middleware('can:cancelInvitation,team')
            ->scopeBindings()
            ->name('teams.invitations.destroy');
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
