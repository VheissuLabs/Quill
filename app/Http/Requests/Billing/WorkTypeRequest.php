<?php

namespace App\Http\Requests\Billing;

use App\Models\Organization;
use App\Models\WorkType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim($this->string('name')->toString())]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');
        $workType = $this->route('workType');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('work_types', 'name')
                    ->where('organization_id', $organization->id)
                    ->ignore($workType instanceof WorkType ? $workType->id : null),
            ],
            'color' => ['sometimes', 'string', Rule::in(self::colors())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, string> */
    public static function colors(): array
    {
        return ['gray', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'violet', 'pink'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => __('That work type already exists.')];
    }
}
