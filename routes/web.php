<?php

use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Issues\IssueController;
use App\Http\Controllers\Issues\IssueOrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationLogoController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\OrganizationRoleController;
use App\Http\Controllers\Portal\CommentController;
use App\Http\Controllers\Portal\FeatureController;
use App\Http\Controllers\Portal\FeatureIssueController;
use App\Http\Controllers\Portal\FeatureStatusController;
use App\Http\Controllers\Portal\FeatureVoteController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Sprints\SprintColumnController;
use App\Http\Controllers\Sprints\SprintController;
use App\Http\Controllers\Sprints\SprintStateController;
use App\Http\Controllers\Teams\BoardColumnController;
use App\Http\Controllers\Teams\BoardColumnOrderController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\RememberPortalPage;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::prefix('portal/{organization}/{project}')
    ->scopeBindings()
    ->name('portal.')
    ->group(function () {
        Route::middleware(RememberPortalPage::class)->group(function () {
            Route::get('/', [PortalProjectController::class, 'show'])->name('projects.show');
            Route::get('features/{feature}', [FeatureController::class, 'show'])->name('features.show');
        });

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::post('features', [FeatureController::class, 'store'])->name('features.store');
            Route::patch('features/{feature}', [FeatureController::class, 'update'])->name('features.update');
            Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->name('features.destroy');
            Route::patch('features/{feature}/status', [FeatureStatusController::class, 'update'])->name('features.status.update');
            Route::post('features/{feature}/issue', [FeatureIssueController::class, 'store'])->name('features.issue.store');
            Route::post('features/{feature}/vote', [FeatureVoteController::class, 'store'])->name('features.vote.store');
            Route::delete('features/{feature}/vote', [FeatureVoteController::class, 'destroy'])->name('features.vote.destroy');
            Route::post('features/{feature}/comments', [CommentController::class, 'store'])->name('features.comments.store');
            Route::delete('features/{feature}/comments/{comment}', [CommentController::class, 'destroy'])->name('features.comments.destroy');
        });
    });

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('projects', ProjectController::class)->except(['create', 'show']);
        Route::resource('clients', ClientController::class)->except(['create', 'show']);
        Route::resource('teams', TeamController::class)->except(['create', 'show']);
        Route::get('roles', [OrganizationRoleController::class, 'index'])->name('roles.index');
        Route::get('roles/{role}', [OrganizationRoleController::class, 'show'])->name('roles.show');
        Route::put('issues/order', [IssueOrderController::class, 'update'])->name('issues.order.update');
        Route::resource('issues', IssueController::class)->except(['create', 'edit']);
        Route::resource('sprints', SprintController::class)->except(['create', 'edit']);
        Route::post('sprints/{sprint}/start', [SprintStateController::class, 'store'])->name('sprints.start');
        Route::delete('sprints/{sprint}/start', [SprintStateController::class, 'destroy'])->name('sprints.complete');
        Route::put('sprints/{sprint}/columns/{column}', [SprintColumnController::class, 'update'])->name('sprints.columns.update');

        Route::post('teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
        Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
        Route::put('teams/{team}/columns/order', [BoardColumnOrderController::class, 'update'])->name('teams.columns.order.update');
        Route::post('teams/{team}/columns', [BoardColumnController::class, 'store'])->name('teams.columns.store');
        Route::patch('teams/{team}/columns/{column}', [BoardColumnController::class, 'update'])->name('teams.columns.update');
        Route::delete('teams/{team}/columns/{column}', [BoardColumnController::class, 'destroy'])->name('teams.columns.destroy');
    });

Route::prefix('{current_organization}/settings')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->name('organizations.')
    ->group(function () {
        Route::get('/', [OrganizationController::class, 'edit'])->name('edit');
        Route::patch('/', [OrganizationController::class, 'update'])->name('update');
        Route::delete('/', [OrganizationController::class, 'destroy'])->name('destroy');
        Route::patch('modules', [OrganizationController::class, 'updateModules'])->name('modules.update');

        Route::post('logo', [OrganizationLogoController::class, 'store'])->name('logo.store');
        Route::delete('logo', [OrganizationLogoController::class, 'destroy'])->name('logo.destroy');

        Route::patch('members/{user}', [OrganizationMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [OrganizationMemberController::class, 'destroy'])->name('members.destroy');

        Route::post('roles', [OrganizationRoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [OrganizationRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [OrganizationRoleController::class, 'destroy'])->name('roles.destroy');

        Route::post('invitations', [OrganizationInvitationController::class, 'store'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])->name('invitations.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('invitations/{invitation}/accept', [OrganizationInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
