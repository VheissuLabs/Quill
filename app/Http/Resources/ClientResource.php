<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'deletedAt' => $this->deleted_at?->toISOString(),
            'projectsCount' => $this->whenCounted('projects'),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn (ClientContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
            ])->all()),
            'domains' => $this->whenLoaded('domains', fn () => $this->domains->map(fn (ClientDomain $domain) => [
                'id' => $domain->id,
                'domain' => $domain->domain,
            ])->all()),
        ];
    }
}
