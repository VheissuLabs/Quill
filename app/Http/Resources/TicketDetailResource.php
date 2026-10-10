<?php

namespace App\Http\Resources;

use App\Models\ClientDomain;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\NotFreeMailDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin Ticket */
class TicketDetailResource extends JsonResource
{
    public function __construct(Ticket $resource, private User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $domain = Str::after($this->requester_email, '@');

        return [
            ...TicketResource::make($this->resource)->resolve(),
            'requesterIsUser' => $this->requester_user_id !== null,
            'createdByName' => $this->creator?->name,
            'suggestedDomain' => NotFreeMailDomain::isFreeMail($domain) || ClientDomain::query()->where('organization_id', $this->organization_id)->where('domain', $domain)->exists()
                ? null
                : $domain,
            'canConvert' => $this->viewer->can('convert', $this->resource),
        ];
    }
}
