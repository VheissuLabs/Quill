<?php

namespace App\Ai\Tools\Concerns;

use App\Models\Organization;
use App\Models\User;

/**
 * Resolves the organization a tool operates on from the asking user, never from
 * the model's arguments.
 *
 * This is the load-bearing security property of every tool. No tool accepts an
 * organization parameter, so a model that is confused, or steered by a prompt
 * injected into a client's message, has no argument through which to reach
 * another tenant's data. Cross-tenant leakage is impossible by construction
 * rather than contingent on the model behaving.
 */
trait ScopedToCurrentOrganization
{
    public function __construct(protected User $user) {}

    protected function organization(): ?Organization
    {
        return $this->user->currentOrganization;
    }

    /**
     * The message returned to the model when the user has no organization.
     *
     * Phrased as a fact for the model to relay rather than an exception, so the
     * assistant explains the situation instead of the request failing.
     */
    protected function withoutOrganization(): string
    {
        return 'The user is not currently working in any organization, so there is nothing to report.';
    }
}
