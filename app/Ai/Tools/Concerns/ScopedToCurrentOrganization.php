<?php

namespace App\Ai\Tools\Concerns;

use App\Models\Organization;
use App\Models\User;

/**
 * Resolves the organization from the asking user, never from the model's
 * arguments. No tool accepts an organization parameter, so a confused or
 * prompt-injected model has nothing to reach another tenant through.
 */
trait ScopedToCurrentOrganization
{
    public function __construct(protected User $user) {}

    protected function organization(): ?Organization
    {
        return $this->user->currentOrganization;
    }

    protected function withoutOrganization(): string
    {
        return 'The user is not currently working in any organization, so there is nothing to report.';
    }

    /** Returned, not thrown, so the assistant explains the refusal rather than the turn erroring. */
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
