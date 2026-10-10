<?php

namespace App\Observers;

use App\Models\ClientContact;

class ClientContactObserver
{
    public function saving(ClientContact $contact): void
    {
        $contact->email = strtolower(trim($contact->email));
        $contact->organization_id = $contact->client->organization_id;
    }
}
