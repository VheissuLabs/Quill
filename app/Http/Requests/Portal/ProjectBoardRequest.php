<?php

namespace App\Http\Requests\Portal;

use App\Enums\FeatureStatus;
use Illuminate\Foundation\Http\FormRequest;

class ProjectBoardRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function status(): ?FeatureStatus
    {
        return FeatureStatus::tryFrom((string) $this->query('status'));
    }

    public function sort(): string
    {
        return $this->query('sort') === 'new' ? 'new' : 'top';
    }
}
