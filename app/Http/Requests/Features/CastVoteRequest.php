<?php

namespace App\Http\Requests\Features;

use App\Enums\VoteDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CastVoteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'direction' => ['sometimes', 'string', Rule::enum(VoteDirection::class)],
        ];
    }

    public function direction(): VoteDirection
    {
        return VoteDirection::from($this->validated('direction') ?? VoteDirection::Up->value);
    }
}
