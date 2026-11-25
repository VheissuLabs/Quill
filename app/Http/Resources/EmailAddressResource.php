<?php

namespace App\Http\Resources;

use App\Models\EmailAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailAddress */
class EmailAddressResource extends JsonResource
{
    /** @return array{id: int, email: string, verified: bool} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'verified' => $this->isVerified(),
        ];
    }
}
