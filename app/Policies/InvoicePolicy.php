<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->manages($user, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->manages($user, $organization);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->manages($user, $invoice->organization);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->manages($user, $invoice->organization)
            && $invoice->status !== InvoiceStatus::Void;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->manages($user, $invoice->organization)
            && $invoice->status === InvoiceStatus::Draft
            && ! $invoice->payments()->exists();
    }

    private function manages(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Invoices)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageInvoices);
    }
}
