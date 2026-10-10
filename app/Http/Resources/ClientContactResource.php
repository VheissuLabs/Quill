<?php

namespace App\Http\Resources;

use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientContact */
class ClientContactResource extends JsonResource
{
    /** @return array{id: int, name: string|null, email: string, client: array{id: int, name: string}|null} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'client' => $this->relationLoaded('client') ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null,
        ];
    }
}
