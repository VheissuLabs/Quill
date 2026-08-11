<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Notifications for the test user, spread across the organizations they belong
     * to so the sidebar popover has something to group.
     *
     * These rows are written directly rather than sent through a Notification
     * class, because no producer exists yet — that is deliverable 5. The `data`
     * shape here is the contract those producers must satisfy: a `title` to
     * render and an `organization_name` to group by.
     *
     * @var array<int, array{organization: string, title: string, minutes: int, read: bool}>
     */
    protected array $notifications = [
        ['organization' => 'NotaryDash', 'title' => 'Jen invited you to the Development team', 'minutes' => 3, 'read' => false],
        ['organization' => 'NotaryDash', 'title' => 'Your role in Design changed to Admin', 'minutes' => 40, 'read' => false],
        ['organization' => 'NotaryDash', 'title' => 'Priya joined Quality Assurance', 'minutes' => 260, 'read' => true],
        ['organization' => '92 Labs', 'title' => 'Jerry invited you to the Platform team', 'minutes' => 95, 'read' => false],
        ['organization' => '92 Labs', 'title' => 'You were removed from Copperfield Dental', 'minutes' => 1500, 'read' => true],
        ['organization' => 'VheissuLabs', 'title' => 'Wavelength Audio was added as a client', 'minutes' => 2900, 'read' => true],
    ];

    public function run(): void
    {
        $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        foreach ($this->notifications as $notification) {
            $organization = Organization::where('name', $notification['organization'])->firstOrFail();
            $createdAt = now()->subMinutes($notification['minutes']);

            $user->notifications()->create([
                'id' => (string) Str::uuid7(),
                'type' => 'App\\Notifications\\Teams\\TeamInvitation',
                'data' => [
                    'title' => $notification['title'],
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                ],
                'read_at' => $notification['read'] ? $createdAt->copy()->addMinutes(1) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
