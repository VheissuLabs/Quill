<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Client = 'client';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<OrganizationPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => OrganizationPermission::cases(),
            self::Admin => [
                OrganizationPermission::UpdateOrganization,
                OrganizationPermission::AddMember,
                OrganizationPermission::UpdateMember,
                OrganizationPermission::RemoveMember,
                OrganizationPermission::CreateInvitation,
                OrganizationPermission::CancelInvitation,
                OrganizationPermission::CreateTeam,
                OrganizationPermission::UpdateTeam,
                OrganizationPermission::CreateClient,
                OrganizationPermission::UpdateClient,
            ],
            self::Member, self::Client => [],
        };
    }

    public function hasPermission(OrganizationPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    public function level(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Member => 2,
            self::Client => 1,
        };
    }

    public function isAtLeast(OrganizationRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    public function isBillable(): bool
    {
        return $this !== self::Client;
    }

    /** @return array<array{value: string, label: string}> */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->reject(fn (self $role) => in_array($role, [self::Owner, self::Client]))
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
