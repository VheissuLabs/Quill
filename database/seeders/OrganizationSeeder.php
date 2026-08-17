<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Concerns\AttributesActivity;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    use AttributesActivity;

    /**
     * The three organizations, with who owns each and what role the test user
     * holds there. Two of them belong to other people — the test user is
     * working for them — which is why the roles differ.
     *
     * @var array<string, array{owner: ?array{name: string, email: string}, role: string}>
     */
    protected array $organizations = [
        'NotaryDash' => [
            'owner' => ['name' => 'Jen', 'email' => 'jen@notarydash.com'],
            'role' => 'admin',
        ],
        '92 Labs' => [
            'owner' => ['name' => 'Jerry', 'email' => 'jerry@92labs.com'],
            'role' => 'member',
        ],
        'VheissuLabs' => [
            'owner' => null,
            'role' => 'owner',
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

                return $this->causedBy($owner, function () use ($name, $owner, $user, $spec) {
                    $organization = Organization::factory()
                        ->withOwner($owner)
                        ->withMembers(2)
                        ->create(['name' => $name]);

                    if (! $owner->is($user)) {
                        $organization->members()->attach($user);
                        $user->assignOrganizationRole($organization, $spec['role']);
                    }

                    return $organization;
                });
            });

        /**
         * Land the user in a real organization rather than the empty personal one
         * they were given at registration, so a freshly seeded app has something
         * to show on the first page load.
         */
        $user->switchOrganization($created->first());
    }
}
