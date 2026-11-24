<?php

namespace App\Http\Requests\Organizations;

use App\Enums\SidebarSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleOrderRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'size:'.count(SidebarSection::cases())],
            'order.*' => ['required', 'string', 'distinct', Rule::enum(SidebarSection::class)],
        ];
    }
}
