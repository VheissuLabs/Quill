<?php

use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizations\OrganizationMembershipController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\DenyClientContacts;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('join/{invitation}', [OrganizationMembershipController::class, 'create'])->name('join.create');
});

Route::post('join/{invitation}', [OrganizationMembershipController::class, 'store'])->name('join.store');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'verified', DenyClientContacts::class])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('assistant', [AssistantController::class, 'show'])->name('assistant');
    Route::post('assistant/messages', [AssistantController::class, 'store'])->name('assistant.messages.store');
});

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');

    Route::put('organizations/{organization}/membership', [OrganizationMembershipController::class, 'update'])
        ->middleware('can:view,organization')
        ->name('organizations.membership.update');

    Route::delete('organization-invitations/{invitation}', [OrganizationMembershipController::class, 'destroy'])
        ->name('organization-invitations.destroy');
});

require __DIR__.'/settings.php';
