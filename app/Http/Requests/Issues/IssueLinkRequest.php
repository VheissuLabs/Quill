<?php

namespace App\Http\Requests\Issues;

use App\Models\Issue;
use App\Models\IssueLink;
use Illuminate\Foundation\Http\FormRequest;

class IssueLinkRequest extends FormRequest
{
    /** A link can be managed from either of the two issues it joins. */
    public function authorize(): bool
    {
        $issue = $this->route('issue');
        $link = $this->route('link');

        if (! $issue instanceof Issue || ! $link instanceof IssueLink) {
            return false;
        }

        return in_array($issue->getKey(), [$link->issue_id, $link->related_issue_id], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
