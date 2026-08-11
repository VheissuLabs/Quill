<?php

namespace App\Concerns;

use App\Rules\ReservedName;
use Illuminate\Contracts\Validation\ValidationRule;

trait OrganizationValidationRules
{
    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function organizationNameRules(): array
    {
        return ['required', 'string', 'max:255', new ReservedName];
    }
}
