<?php

use App\Http\Controllers\ClearNotificationsController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Issues\IssueController;
use App\Http\Controllers\Issues\IssueOrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationReadController;
use App\Http\Controllers\Organizations\AcceptOrganizationInvitationController;
use App\Http\Controllers\Organizations\DeclineOrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationLogoController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use App\Http\Controllers\Organizations\OrganizationModuleController;
use App\Http\Controllers\Organizations\OrganizationRoleController;
use App\Http\Controllers\Portal\CommentController;
use App\Http\Controllers\Portal\FeatureController;
use App\Http\Controllers\Portal\FeatureIssueController;
use App\Http\Controllers\Portal\FeatureStatusController;
use App\Http\Controllers\Portal\FeatureVoteController;
use App\Http\Controllers\Portal\ProjectController as PortalProjectController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\ReadAllNotificationsController;
use App\Http\Controllers\Sprints\SprintColumnController;
use App\Http\Controllers\Sprints\SprintController;
use App\Http\Controllers\Sprints\SprintStateController;
use App\Http\Controllers\Teams\BoardColumnController;
use App\Http\Controllers\Teams\BoardColumnOrderController;
use App\Http\Controllers\Teams\CurrentTeamController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\RedirectGuestsFromPrivateProject;
use App\Http\Middleware\RememberPortalPage;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::get('/', HomeController::class)->name('home');

Route::prefix('portal/{organization}/{project}')
    ->scopeBindings()
    ->name('portal.')
    ->group(function () {
        Route::middleware([RememberPortalPage::class, RedirectGuestsFromPrivateProject::class])->group(function () {
            Route::get('/', [PortalProjectController::class, 'show'])->can('viewFeatureBoard', 'project')->name('projects.show');
            Route::get('features/{feature}', [FeatureController::class, 'show'])->can('view', 'feature')->name('features.show');
        });

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::post('features', [FeatureController::class, 'store'])->can('create', [Feature::class, 'project'])->name('features.store');
            Route::patch('features/{feature}', [FeatureController::class, 'update'])->can('update', 'feature')->name('features.update');
            Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->can('delete', 'feature')->name('features.destroy');
            Route::patch('features/{feature}/status', [FeatureStatusController::class, 'update'])->can('updateStatus', 'feature')->name('features.status.update');
            Route::post('features/{feature}/issue', [FeatureIssueController::class, 'store'])->can('convert', 'feature')->name('features.issue.store');
            Route::post('features/{feature}/vote', [FeatureVoteController::class, 'store'])->can('vote', 'feature')->name('features.vote.store');
            Route::delete('features/{feature}/vote', [FeatureVoteController::class, 'destroy'])->can('vote', 'feature')->name('features.vote.destroy');
            Route::post('features/{feature}/comments', [CommentController::class, 'store'])->can('comment', 'feature')->name('features.comments.store');
            Route::delete('features/{feature}/comments/{comment}', [CommentController::class, 'destroy'])->can('delete', 'comment')->name('features.comments.destroy');
        });
    });

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('projects', ProjectController::class)->except(['create', 'show'])
            ->middlewareFor('index', 'can:viewAny,'.Project::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Project::class.',current_organization')
            ->middlewareFor(['edit', 'update'], 'can:update,project')
            ->middlewareFor('destroy', 'can:delete,project');
        Route::resource('clients', ClientController::class)->except(['create', 'show'])
            ->middlewareFor('index', 'can:viewAny,'.Client::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Client::class.',current_organization')
            ->middlewareFor('edit', 'can:view,client')
            ->middlewareFor('update', 'can:update,client')
            ->middlewareFor('destroy', 'can:delete,client');
        Route::resource('teams', TeamController::class)->except(['create', 'show'])
            ->middlewareFor('index', 'can:viewAny,'.Team::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Team::class.',current_organization')
            ->middlewareFor('edit', 'can:view,team')
            ->middlewareFor('update', 'can:update,team')
            ->middlewareFor('destroy', 'can:delete,team');
        Route::get('roles', [OrganizationRoleController::class, 'index'])->can('viewAny', [Role::class, 'current_organization'])->name('roles.index');
        Route::get('roles/{role}', [OrganizationRoleController::class, 'show'])->can('viewAny', [Role::class, 'current_organization'])->name('roles.show');
        Route::put('issues/order', [IssueOrderController::class, 'update'])->can('viewAny', [Issue::class, 'current_organization'])->name('issues.order.update');
        Route::resource('issues', IssueController::class)->except(['create', 'edit'])
            ->middlewareFor('index', 'can:viewAny,'.Issue::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Issue::class.',current_organization')
            ->middlewareFor('show', 'can:view,issue')
            ->middlewareFor('update', 'can:update,issue')
            ->middlewareFor('destroy', 'can:delete,issue');
        Route::resource('sprints', SprintController::class)->except(['create', 'edit'])
            ->middlewareFor('index', 'can:viewAny,'.Sprint::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Sprint::class.',current_organization')
            ->middlewareFor('show', 'can:view,sprint')
            ->middlewareFor('update', 'can:update,sprint')
            ->middlewareFor('destroy', 'can:delete,sprint');
        Route::post('sprints/{sprint}/start', [SprintStateController::class, 'store'])->can('update', 'sprint')->name('sprints.start');
        Route::delete('sprints/{sprint}/start', [SprintStateController::class, 'destroy'])->can('update', 'sprint')->name('sprints.complete');
        Route::put('sprints/{sprint}/columns/{column}', [SprintColumnController::class, 'update'])->can('view', 'sprint')->name('sprints.columns.update');

        Route::post('teams/{team}/switch', [CurrentTeamController::class, 'store'])->name('teams.switch');
        Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->can('update', 'team')->name('teams.members.store');
        Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->can('update', 'team')->name('teams.members.destroy');
        Route::put('teams/{team}/columns/order', [BoardColumnOrderController::class, 'update'])->can('create', [BoardColumn::class, 'team'])->name('teams.columns.order.update');
        Route::post('teams/{team}/columns', [BoardColumnController::class, 'store'])->can('create', [BoardColumn::class, 'team'])->name('teams.columns.store');
        Route::patch('teams/{team}/columns/{column}', [BoardColumnController::class, 'update'])->can('update', 'column')->name('teams.columns.update');
        Route::delete('teams/{team}/columns/{column}', [BoardColumnController::class, 'destroy'])->can('delete', 'column')->name('teams.columns.destroy');
    });

Route::prefix('{current_organization}/settings')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->name('organizations.')
    ->group(function () {
        Route::get('/', [OrganizationController::class, 'edit'])->name('edit');
        Route::patch('/', [OrganizationController::class, 'update'])->can('update', 'current_organization')->name('update');
        Route::delete('/', [OrganizationController::class, 'destroy'])->name('destroy');
        Route::patch('modules', [OrganizationModuleController::class, 'update'])->can('manageModules', 'current_organization')->name('modules.update');

        Route::post('logo', [OrganizationLogoController::class, 'store'])->can('update', 'current_organization')->name('logo.store');
        Route::delete('logo', [OrganizationLogoController::class, 'destroy'])->can('update', 'current_organization')->name('logo.destroy');

        Route::patch('members/{user}', [OrganizationMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [OrganizationMemberController::class, 'destroy'])->name('members.destroy');

        Route::scopeBindings()->group(function () {
            Route::post('roles', [OrganizationRoleController::class, 'store'])->can('create', [Role::class, 'current_organization'])->name('roles.store');
            Route::patch('roles/{role}', [OrganizationRoleController::class, 'update'])->can('update', 'role')->name('roles.update');
            Route::delete('roles/{role}', [OrganizationRoleController::class, 'destroy'])->can('delete', 'role')->name('roles.destroy');
        });

        Route::post('invitations', [OrganizationInvitationController::class, 'store'])->can('create', [OrganizationInvitation::class, 'current_organization'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])->can('delete', 'invitation')->name('invitations.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('notifications/read', ReadAllNotificationsController::class)->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationReadController::class, 'store'])->name('notifications.read');
    Route::delete('notifications', ClearNotificationsController::class)->name('notifications.destroy-all');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('invitations/{invitation}/accept', AcceptOrganizationInvitationController::class)->name('invitations.accept');
    Route::delete('invitations/{invitation}', DeclineOrganizationInvitationController::class)->name('invitations.decline');
});

require __DIR__.'/settings.php';
