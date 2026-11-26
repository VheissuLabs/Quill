<?php

namespace App\Http\Resources;

use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientContact */
class ClientContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'email' => $this->email,
            'phone' => $this->phone,
            'addresses' => $this->whenLoaded('addresses', fn () => AddressResource::collection($this->addresses)->resolve()),
            'client' => $this->relationLoaded('client') ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null,
        ];
    }
}
