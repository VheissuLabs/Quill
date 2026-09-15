<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardColumnOrderRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'column_ids' => ['required', 'array', 'size:'.$team->columns()->count()],
            'column_ids.*' => ['integer', 'distinct', Rule::exists('board_columns', 'id')->where('owner_type', $team->getMorphClass())->where('owner_id', $team->getKey())],
        ];
    }
}
