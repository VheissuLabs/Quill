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

    /**
     * Names the missing permission rather than the user's role. Which bundle grants
     * it is an organization setting, so the permission is the durable fact and the
     * one an owner can act on.
     */
    protected function refused(string $action, string $permission): string
    {
        return sprintf(
            'The user does not have permission to %s in this organization. They are missing [%s]. Nothing was changed.',
            $action,
            $permission,
        );
    }
}
