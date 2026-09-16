<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class IssueActivityResource extends ActivityResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Issue $issue */
        $issue = $this->subject;

        return [
            ...parent::toArray($request),
            'issue' => [
                'id' => $issue->id,
                'reference' => $issue->reference(),
                'title' => $issue->title,
            ],
        ];
    }
}
