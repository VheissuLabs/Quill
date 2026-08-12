<?php

use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\Assistant\AssistantMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizations\JoinOrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\DenyClientContacts;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

/*
 * Open to guests: someone invited who has no account yet arrives here from their
 * email. Fortify's register route is the wrong door — it requires an organization
 * name and creates one, and an invited contact is joining someone else's.
 */
Route::middleware('guest')->group(function () {
    Route::get('join/{invitation}', [JoinOrganizationController::class, 'show'])->name('join.show');
    Route::post('join/{invitation}', [JoinOrganizationController::class, 'store'])->name('join.store');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

/*
 * The assistant is scoped to the user's current organization, not to a team, so
 * it sits outside the {current_team} prefix the rest of the app still uses.
 */
Route::middleware(['auth', 'verified', DenyClientContacts::class])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('assistant', AssistantController::class)->name('assistant');
    Route::post('assistant/messages', [AssistantMessageController::class, 'store'])->name('assistant.messages.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');

    Route::post('organization-invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])
        ->name('organization-invitations.accept');
    Route::delete('organization-invitations/{invitation}', [OrganizationInvitationController::class, 'decline'])
        ->name('organization-invitations.decline');
});

require __DIR__.'/settings.php';
