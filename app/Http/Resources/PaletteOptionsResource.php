<?php

namespace App\Http\Resources;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Enums\ProjectVisibility;
use App\Http\Requests\Billing\WorkTypeRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Organization */
class PaletteOptionsResource extends JsonResource
{
    public function __construct(Organization $resource, private User $user)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $canInvoice = $this->user->can('create', [Invoice::class, $this->resource]);

        return [
            'issue' => $this->canUse(OrganizationCapability::Issues)
                ? IssueFormResource::make($this->resource, $this->user, $this->user->currentTeamFor($this->resource))->resolve()
                : null,
            'visibilities' => ProjectVisibility::options(),
            'clients' => $this->user->can('create', [Client::class, $this->resource]) || $canInvoice || $this->user->can('create', [Ticket::class, $this->resource])
                ? $this->clients()->orderBy('name')->get(['id', 'name', 'slug'])->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name, 'slug' => $client->slug])->all()
                : [],
            'invoiceProjects' => $canInvoice
                ? $this->projects()->whereNotNull('client_id')->with('client:id,billed_through_client_id')->orderBy('name')->get()
                    ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name, 'billToClientId' => $project->client->billed_through_client_id ?? $project->client_id])->all()
                : [],
            'feedbackProjects' => $this->canUse(OrganizationCapability::Boards)
                ? $this->projects()->visibleTo($this->user)->orderBy('name')->get(['projects.id', 'projects.name', 'projects.slug'])
                    ->map(fn (Project $project) => ['name' => $project->name, 'slug' => $project->slug])->all()
                : [],
            'sprintBoard' => $this->user->boardOwnerFor($this->resource)->name,
            'roles' => $this->user->can('create', [OrganizationInvitation::class, $this->resource])
                ? RoleOptionResource::collection($this->assignableRoles())->resolve()
                : [],
            'permissions' => $this->user->can('create', [Role::class, $this->resource])
                ? OrganizationPermission::options()
                : [],
            'workTypeColors' => $this->user->can('create', [WorkType::class, $this->resource])
                ? WorkTypeRequest::colors()
                : [],
        ];
    }
}
