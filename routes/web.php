<?php

use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\Assistant\AssistantMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

/*
 * The assistant is scoped to the user's current organization, not to a team, so
 * it sits outside the {current_team} prefix the rest of the app still uses.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('assistant', AssistantController::class)->name('assistant');
    Route::post('assistant/messages', [AssistantMessageController::class, 'store'])->name('assistant.messages.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
