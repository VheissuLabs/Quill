<?php

use App\Http\Controllers\Addresses\ClientAddressController;
use App\Http\Controllers\Addresses\ClientContactAddressController;
use App\Http\Controllers\Addresses\OrganizationAddressController;
use App\Http\Controllers\Billing\BillingSettingController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\InvoiceDuplicateController;
use App\Http\Controllers\Billing\InvoiceLineController;
use App\Http\Controllers\Billing\InvoiceLineOrderController;
use App\Http\Controllers\Billing\InvoicePaymentController;
use App\Http\Controllers\Billing\InvoicePreviewController;
use App\Http\Controllers\Billing\InvoiceSendController;
use App\Http\Controllers\Billing\InvoiceVoidController;
use App\Http\Controllers\Billing\PublicInvoiceController;
use App\Http\Controllers\Billing\PublicInvoicePaymentController;
use App\Http\Controllers\Billing\SquareWebhookController;
use App\Http\Controllers\Billing\WorkTypeController;
use App\Http\Controllers\ClearNotificationsController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientDomainController;
use App\Http\Controllers\Clients\ContactController;
use App\Http\Controllers\Clients\RestoreClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\IssueDocumentController;
use App\Http\Controllers\Documents\ProjectDocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Issues\CommentController as IssueCommentController;
use App\Http\Controllers\Issues\IssueController;
use App\Http\Controllers\Issues\IssueLinkController;
use App\Http\Controllers\Issues\IssueLookupController;
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
use App\Http\Controllers\Organizations\OrganizationModuleOrderController;
use App\Http\Controllers\Organizations\OrganizationRoleController;
use App\Http\Controllers\Palette\PaletteController;
use App\Http\Controllers\Palette\PaletteOptionsController;
use App\Http\Controllers\Portal\CommentController;
use App\Http\Controllers\Portal\FeedbackController;
use App\Http\Controllers\Portal\FeedbackIssueController;
use App\Http\Controllers\Portal\FeedbackStatusController;
use App\Http\Controllers\Portal\FeedbackVoteController;
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
use App\Http\Controllers\Teams\TeamModuleController;
use App\Http\Controllers\Tickets\TicketAttachmentController;
use App\Http\Controllers\Tickets\TicketController;
use App\Http\Controllers\Tickets\TicketIssueController;
use App\Http\Controllers\Tickets\TicketReplyController;
use App\Http\Controllers\Tickets\TicketReplyDeliveryController;
use App\Http\Controllers\Webhooks\ResendWebhookController;
use App\Http\Middleware\DiscourageIndexing;
use App\Http\Middleware\EnsureOrganizationMembership;
use App\Http\Middleware\RedirectGuestsFromPrivateProject;
use App\Http\Middleware\RememberPortalPage;
use App\Http\Middleware\VerifyResendSignature;
use App\Models\BillingSetting;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Feedback;
use App\Models\Invoice;
use App\Models\Issue;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\WorkType;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::get('/', HomeController::class)->name('home');

Route::get('invoice/{invoice:public_token}', [PublicInvoiceController::class, 'show'])->middleware(DiscourageIndexing::class)->name('invoices.public');
Route::post('invoice/{invoice:public_token}/pay', [PublicInvoicePaymentController::class, 'store'])->middleware('throttle:10,1')->name('invoices.public.pay');

Route::post('webhooks/resend', ResendWebhookController::class)
    ->middleware(VerifyResendSignature::class)
    ->name('webhooks.resend');

Route::post('webhooks/square/{billingSetting:square_webhook_token}', SquareWebhookController::class)->name('webhooks.square');

Route::prefix('portal/{organization}/{project}')
    ->scopeBindings()
    ->name('portal.')
    ->group(function () {
        Route::middleware([RememberPortalPage::class, RedirectGuestsFromPrivateProject::class])->group(function () {
            Route::get('/', [PortalProjectController::class, 'show'])->can('viewFeedbackBoard', 'project')->name('projects.show');
            Route::get('feedback/{feedback}', [FeedbackController::class, 'show'])->can('view', 'feedback')->name('feedback.show');
        });

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::post('feedback', [FeedbackController::class, 'store'])->can('create', [Feedback::class, 'project'])->name('feedback.store');
            Route::patch('feedback/{feedback}', [FeedbackController::class, 'update'])->can('update', 'feedback')->name('feedback.update');
            Route::delete('feedback/{feedback}', [FeedbackController::class, 'destroy'])->can('delete', 'feedback')->name('feedback.destroy');
            Route::patch('feedback/{feedback}/status', [FeedbackStatusController::class, 'update'])->can('updateStatus', 'feedback')->name('feedback.status.update');
            Route::post('feedback/{feedback}/issue', [FeedbackIssueController::class, 'store'])->can('convert', 'feedback')->name('feedback.issue.store');
            Route::post('feedback/{feedback}/vote', [FeedbackVoteController::class, 'store'])->can('vote', 'feedback')->name('feedback.vote.store');
            Route::delete('feedback/{feedback}/vote', [FeedbackVoteController::class, 'destroy'])->can('vote', 'feedback')->name('feedback.vote.destroy');
            Route::post('feedback/{feedback}/comments', [CommentController::class, 'store'])->can('comment', 'feedback')->name('feedback.comments.store');
            Route::delete('feedback/{feedback}/comments/{comment}', [CommentController::class, 'destroy'])->can('delete', 'comment')->name('feedback.comments.destroy');
        });
    });

Route::prefix('{current_organization}')
    ->middleware(['auth', 'verified', EnsureOrganizationMembership::class])
    ->scopeBindings()
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('palette', [PaletteController::class, 'index'])->name('palette.search');
        Route::get('palette/options', [PaletteOptionsController::class, 'index'])->name('palette.options');

        Route::resource('projects', ProjectController::class)->except(['create', 'show'])
            ->middlewareFor('index', 'can:viewAny,'.Project::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Project::class.',current_organization')
            ->middlewareFor(['edit', 'update'], 'can:update,project')
            ->middlewareFor('destroy', 'can:delete,project');
        Route::resource('clients', ClientController::class)->except(['create'])
            ->middlewareFor('index', 'can:viewAny,'.Client::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Client::class.',current_organization')
            ->middlewareFor(['show', 'edit'], 'can:view,client')
            ->middlewareFor('update', 'can:update,client')
            ->middlewareFor('destroy', 'can:delete,client')
            ->withTrashed(['show']);
        Route::resource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
            ->middlewareFor('index', 'can:viewAny,'.Invoice::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Invoice::class.',current_organization')
            ->middlewareFor('show', 'can:view,invoice')
            ->middlewareFor('update', 'can:update,invoice')
            ->middlewareFor('destroy', 'can:delete,invoice');
        Route::get('invoices/{invoice}/preview', InvoicePreviewController::class)->can('view', 'invoice')->name('invoices.preview');
        Route::post('invoices/{invoice}/send', [InvoiceSendController::class, 'store'])->can('send', 'invoice')->name('invoices.send.store');
        Route::post('invoices/{invoice}/void', [InvoiceVoidController::class, 'store'])->can('void', 'invoice')->name('invoices.void.store');
        Route::post('invoices/{invoice}/duplicate', [InvoiceDuplicateController::class, 'store'])->can('duplicate', 'invoice')->name('invoices.duplicate.store');
        Route::put('invoices/{invoice}/lines/order', [InvoiceLineOrderController::class, 'update'])->can('editLines', 'invoice')->name('invoices.lines.order.update');
        Route::resource('invoices.lines', InvoiceLineController::class)->only(['store', 'update', 'destroy'])
            ->middlewareFor(['store', 'destroy'], 'can:editLines,invoice')
            ->middlewareFor('update', 'can:update,invoice');
        Route::resource('invoices.payments', InvoicePaymentController::class)->only(['store', 'update', 'destroy'])
            ->middlewareFor(['store', 'update', 'destroy'], 'can:update,invoice');
        Route::get('contacts', [ContactController::class, 'index'])->can('viewAny', [Client::class, 'current_organization'])->name('contacts.index');
        Route::get('client-contacts', [ClientContactController::class, 'index'])->can('create', [Ticket::class, 'current_organization'])->name('client-contacts.index');
        Route::post('clients/{client}/restore', RestoreClientController::class)->withTrashed()->can('restore', 'client')->name('clients.restore');
        Route::post('clients/{client}/contacts', [ClientContactController::class, 'store'])->can('update', 'client')->name('clients.contacts.store');
        Route::get('clients/{client}/contacts/{contact}', [ClientContactController::class, 'show'])->can('view', 'client')->name('clients.contacts.show');
        Route::patch('clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->can('update', 'client')->name('clients.contacts.update');
        Route::delete('clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->can('update', 'client')->name('clients.contacts.destroy');
        Route::post('clients/{client}/addresses', [ClientAddressController::class, 'store'])->can('update', 'client')->name('clients.addresses.store');
        Route::patch('clients/{client}/addresses/{address}', [ClientAddressController::class, 'update'])->can('update', 'client')->name('clients.addresses.update');
        Route::delete('clients/{client}/addresses/{address}', [ClientAddressController::class, 'destroy'])->can('update', 'client')->name('clients.addresses.destroy');
        Route::post('clients/{client}/contacts/{contact}/addresses', [ClientContactAddressController::class, 'store'])->can('update', 'client')->name('clients.contacts.addresses.store');
        Route::patch('clients/{client}/contacts/{contact}/addresses/{address}', [ClientContactAddressController::class, 'update'])->can('update', 'client')->name('clients.contacts.addresses.update');
        Route::delete('clients/{client}/contacts/{contact}/addresses/{address}', [ClientContactAddressController::class, 'destroy'])->can('update', 'client')->name('clients.contacts.addresses.destroy');
        Route::post('clients/{client}/domains', [ClientDomainController::class, 'store'])->can('update', 'client')->name('clients.domains.store');
        Route::delete('clients/{client}/domains/{domain}', [ClientDomainController::class, 'destroy'])->can('update', 'client')->name('clients.domains.destroy');
        Route::resource('teams', TeamController::class)->except(['create', 'show'])
            ->middlewareFor('index', 'can:viewAny,'.Team::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Team::class.',current_organization')
            ->middlewareFor('edit', 'can:view,team')
            ->middlewareFor('update', 'can:update,team')
            ->middlewareFor('destroy', 'can:delete,team');
        Route::get('roles', [OrganizationRoleController::class, 'index'])->can('viewAny', [Role::class, 'current_organization'])->name('roles.index');
        Route::get('roles/{role}', [OrganizationRoleController::class, 'show'])->can('viewAny', [Role::class, 'current_organization'])->name('roles.show');
        Route::get('issues/lookup', [IssueLookupController::class, 'index'])->can('viewAny', [Issue::class, 'current_organization'])->name('issues.lookup');
        Route::put('issues/order', [IssueOrderController::class, 'update'])->can('viewAny', [Issue::class, 'current_organization'])->name('issues.order.update');
        Route::resource('issues', IssueController::class)->except(['create', 'edit'])
            ->middlewareFor('index', 'can:viewAny,'.Issue::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Issue::class.',current_organization')
            ->middlewareFor('show', 'can:view,issue')
            ->middlewareFor('update', 'can:update,issue')
            ->middlewareFor('destroy', 'can:delete,issue');
        Route::post('issues/{issue}/documents', [IssueDocumentController::class, 'store'])->can('update', 'issue')->name('issues.documents.store');
        Route::post('projects/{project}/documents', [ProjectDocumentController::class, 'store'])->can('update', 'project')->name('projects.documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->withoutScopedBindings()->can('view', 'document')->name('documents.show');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->withoutScopedBindings()->can('delete', 'document')->name('documents.destroy');
        Route::patch('issues/{issue}/links/{link}', [IssueLinkController::class, 'update'])->withoutScopedBindings()->can('update', 'issue')->name('issues.links.update');
        Route::delete('issues/{issue}/links/{link}', [IssueLinkController::class, 'destroy'])->withoutScopedBindings()->can('update', 'issue')->name('issues.links.destroy');
        Route::post('issues/{issue}/comments', [IssueCommentController::class, 'store'])->can('comment', 'issue')->name('issues.comments.store');
        Route::delete('issues/{issue}/comments/{comment}', [IssueCommentController::class, 'destroy'])->can('delete', 'comment')->name('issues.comments.destroy');

        Route::resource('tickets', TicketController::class)->only(['index', 'store', 'show', 'update'])
            ->middlewareFor('index', 'can:viewAny,'.Ticket::class.',current_organization')
            ->middlewareFor('store', 'can:create,'.Ticket::class.',current_organization')
            ->middlewareFor('show', 'can:view,ticket')
            ->middlewareFor('update', 'can:update,ticket');
        Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->can('reply', 'ticket')->name('tickets.replies.store');
        Route::delete('tickets/{ticket}/replies/{comment}', [TicketReplyController::class, 'destroy'])->can('delete', 'comment')->name('tickets.replies.destroy');
        Route::post('tickets/{ticket}/replies/{comment}/delivery', [TicketReplyDeliveryController::class, 'store'])->can('reply', 'ticket')->name('tickets.replies.delivery.store');
        Route::post('tickets/{ticket}/issue', [TicketIssueController::class, 'store'])->can('convert', 'ticket')->name('tickets.issue.store');
        Route::delete('tickets/{ticket}/issue', [TicketIssueController::class, 'destroy'])->can('update', 'ticket')->name('tickets.issue.destroy');
        Route::get('ticket-attachments/{attachment}', [TicketAttachmentController::class, 'show'])->withoutScopedBindings()->can('view', 'attachment')->name('tickets.attachments.show');

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
        Route::patch('teams/{team}/modules', [TeamModuleController::class, 'update'])->can('update', 'team')->name('teams.modules.update');
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
        Route::patch('modules/order', [OrganizationModuleOrderController::class, 'update'])->can('manageModules', 'current_organization')->name('modules.order.update');

        Route::post('logo', [OrganizationLogoController::class, 'store'])->can('update', 'current_organization')->name('logo.store');
        Route::delete('logo', [OrganizationLogoController::class, 'destroy'])->can('update', 'current_organization')->name('logo.destroy');

        Route::patch('members/{user}', [OrganizationMemberController::class, 'update'])->name('members.update');
        Route::delete('members/{user}', [OrganizationMemberController::class, 'destroy'])->name('members.destroy');

        Route::scopeBindings()->group(function () {
            Route::post('roles', [OrganizationRoleController::class, 'store'])->can('create', [Role::class, 'current_organization'])->name('roles.store');
            Route::patch('roles/{role}', [OrganizationRoleController::class, 'update'])->can('update', 'role')->name('roles.update');
            Route::delete('roles/{role}', [OrganizationRoleController::class, 'destroy'])->can('delete', 'role')->name('roles.destroy');
        });

        Route::post('addresses', [OrganizationAddressController::class, 'store'])->can('update', 'current_organization')->name('addresses.store');
        Route::scopeBindings()->group(function () {
            Route::patch('addresses/{address}', [OrganizationAddressController::class, 'update'])->can('update', 'current_organization')->name('addresses.update');
            Route::delete('addresses/{address}', [OrganizationAddressController::class, 'destroy'])->can('update', 'current_organization')->name('addresses.destroy');
        });

        Route::get('billing', [BillingSettingController::class, 'edit'])->can('viewAny', [BillingSetting::class, 'current_organization'])->name('billing.edit');
        Route::patch('billing', [BillingSettingController::class, 'update'])->can('update', [BillingSetting::class, 'current_organization'])->name('billing.update');

        Route::post('work-types', [WorkTypeController::class, 'store'])->can('create', [WorkType::class, 'current_organization'])->name('work-types.store');
        Route::scopeBindings()->group(function () {
            Route::patch('work-types/{workType}', [WorkTypeController::class, 'update'])->can('update', 'workType')->name('work-types.update');
            Route::delete('work-types/{workType}', [WorkTypeController::class, 'destroy'])->can('delete', 'workType')->name('work-types.destroy');
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
