<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * The three organizations, with who owns each and what role the test user
     * holds there. Two of them belong to other people — the test user is
     * working for them — which is why the roles differ.
     *
     * @var array<string, array{owner: ?array{name: string, email: string}, role: OrganizationRole}>
     */
    protected array $organizations = [
        'NotaryDash' => [
            'owner' => ['name' => 'Jen', 'email' => 'jen@notarydash.com'],
            'role' => OrganizationRole::Admin,
        ],
        '92 Labs' => [
            'owner' => ['name' => 'Jerry', 'email' => 'jerry@92labs.com'],
            'role' => OrganizationRole::Member,
        ],
        'VheissuLabs' => [
            'owner' => null,
            'role' => OrganizationRole::Owner,
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        $created = collect($this->organizations)
            ->map(function (array $spec, string $name) use ($user) {
                $owner = $spec['owner'] === null
                    ? $user
                    : User::factory()->create($spec['owner']);

                $organization = Organization::factory()
                    ->withOwner($owner)
                    ->withMembers(2)
                    ->create(['name' => $name]);

                if (! $owner->is($user)) {
                    $organization->members()->attach($user, [
                        'role' => $spec['role']->value,
                    ]);
                }

                return $organization;
            });

        /**
         * Land the user in a real organization rather than the empty personal one
         * they were given at registration, so a freshly seeded app has something
         * to show on the first page load.
         */
        $user->switchOrganization($created->first());
    }
}
