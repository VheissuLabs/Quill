<?php

namespace App\Concerns;

use App\Data\ActivityEntry;
use App\Models\Activity;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use Illuminate\Support\Str;

trait SummarizesActivity
{
    protected function toActivityEntry(Activity $activity): ActivityEntry
    {
        return new ActivityEntry(
            id: (string) $activity->getKey(),
            summary: $this->summarize($activity),
            causerName: $activity->causer?->getAttribute('name'),
            happenedAt: (string) $activity->created_at?->toDayDateTimeString(),
            happenedAtDiff: (string) $activity->created_at?->diffForHumans(),
        );
    }

    /**
     * A readable sentence, because "updated" against a UUID tells an admin
     * nothing about what happened to their organization.
     */
    protected function summarize(Activity $activity): string
    {
        $subjectModel = $activity->subject;

        if ($subjectModel instanceof OrganizationMembership) {
            return match ($activity->event) {
                'created' => trim($subjectModel->user->name.' joined the organization'),
                'deleted' => trim($subjectModel->user->name.' left the organization'),
                default => trim('Changed '.$subjectModel->user->name.'\'s membership'),
            };
        }

        if ($subjectModel instanceof OrganizationInvitation) {
            return match ($activity->event) {
                'created' => "Invited {$subjectModel->email}",
                'deleted' => "Withdrew the invitation for {$subjectModel->email}",
                default => $subjectModel->isAccepted()
                    ? "{$subjectModel->email} accepted their invitation"
                    : "Updated the invitation for {$subjectModel->email}",
            };
        }

        $subject = Str::lower(Str::headline(class_basename((string) $activity->subject_type)));
        $changes = $activity->attribute_changes ?? [];
        $before = $changes['old']['name'] ?? null;
        $after = $changes['attributes']['name'] ?? null;
        $name = $after ?? $activity->subject?->getAttribute('name') ?? $changes['attributes']['email'] ?? null;

        return match ($activity->event) {
            'created' => trim("Created {$subject} ".($name ?? '')),
            'deleted' => trim("Deleted {$subject} ".($name ?? '')),
            'updated' => $before !== null && $after !== null && $before !== $after
                ? "Renamed {$subject} {$before} to {$after}"
                : trim("Updated {$subject} ".($name ?? '')),
            default => trim(ucfirst((string) $activity->event)." {$subject} ".($name ?? '')),
        };
    }
}
