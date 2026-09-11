<?php

namespace App\Http\Requests\Issues;

use App\Enums\IssueStatus;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'status' => ['sometimes', 'required', 'string', Rule::in([IssueStatus::Backlog->value, IssueStatus::Done->value, IssueStatus::Cancelled->value])],
            'board_column_id' => ['sometimes', 'required', 'integer', Rule::exists('board_columns', 'id')->whereIn('team_id', $organization->teams()->pluck('id'))],
            ...collect(IssueRules::for($organization))
                ->map(fn (array $rules) => ['sometimes', ...$rules])
                ->all(),
        ];
    }
}
