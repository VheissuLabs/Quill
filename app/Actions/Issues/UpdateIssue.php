<?php

namespace App\Actions\Issues;

use App\Data\IssueChangesData;
use App\Enums\IssueStatus;
use App\Models\BoardColumn;
use App\Models\Issue;
use Illuminate\Support\Facades\DB;

class UpdateIssue
{
    public function handle(Issue $issue, IssueChangesData $changes): Issue
    {
        return DB::transaction(function () use ($issue, $changes): Issue {
            $issue->update($changes->attributes);

            if (! $changes->movesIssue) {
                return $issue;
            }

            $destination = $changes->destination;

            match (true) {
                $destination instanceof BoardColumn => $issue->moveToColumn($destination),
                $destination instanceof IssueStatus => $issue->changeStatus($destination),
                default => $issue->planInto($destination),
            };

            return $issue;
        });
    }
}
