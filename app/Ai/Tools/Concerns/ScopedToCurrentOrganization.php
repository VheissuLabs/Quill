<?php

namespace App\Ai\Tools\Concerns;

use App\Models\Organization;
use App\Models\User;

trait ScopedToCurrentOrganization
{
    public function __construct(protected User $user) {}

    protected function organization(): ?Organization
    {
        $organization = $this->user->currentOrganization;

        if ($organization === null || ! $this->user->belongsToOrganization($organization)) {
            return null;
        }

        return $organization;
    }

    protected function withoutOrganization(): string
    {
        return 'The user is not currently working in any organization, so there is nothing to report.';
    }

    protected function refused(string $action): string
    {
        $role = $this->user->currentOrganization === null
            ? null
            : $this->user->organizationRole($this->user->currentOrganization);

        return sprintf(
            'The user does not have permission to %s in this organization. Their role is %s. Nothing was changed.',
            $action,
            $role?->label() ?? 'unknown',
        );
    }
}
