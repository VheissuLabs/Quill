<?php

namespace App\Observers;

use App\Models\ClientDomain;

class ClientDomainObserver
{
    public function saving(ClientDomain $domain): void
    {
        $domain->domain = strtolower(trim($domain->domain));
        $domain->organization_id ??= $domain->client->organization_id;
    }
}
