<?php

namespace App\Observers;

use App\Models\Issue;
use RuntimeException;

class IssueReporterObserver
{
    /**
     * A contact's issue must name the client they represent. Nothing else in the
     * app can show a contact's issue back to them without it.
     */
    public function saving(Issue $issue): void
    {
        $reporter = $issue->reporter;
        $organization = $issue->organization;

        if ($reporter === null || $organization === null) {
            return;
        }

        if (! $reporter->isClientContact($organization)) {
            return;
        }

        $contactClientId = $reporter->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->value('client_id');

        if ($issue->client_id !== $contactClientId) {
            throw new RuntimeException('A contact\'s issue must belong to the client they represent.');
        }
    }
}
