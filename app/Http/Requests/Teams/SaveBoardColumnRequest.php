<?php

namespace App\Http\Requests\Teams;

use App\Enums\IssueStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBoardColumnRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', Rule::in(array_map(fn (IssueStatus $status) => $status->value, IssueStatus::columnStatuses()))],
        ];
    }
}
