<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(config('filesystems.default'));
});

test('owners can upload a logo and it shows on the portal until it is removed', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $project = Project::factory()->for($organization)->public()->create();

    $this->actingAs($owner)
        ->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png')])
        ->assertRedirect(route('organizations.edit', $organization));

    $path = $organization->fresh()->logo_path;
    expect($path)->toStartWith('organization-logos/');
    Storage::disk(config('filesystems.default'))->assertExists($path);

    $this->get(route('portal.projects.show', [$organization, $project]))
        ->assertInertia(fn (Assert $page) => $page->where('organization.logoUrl', Storage::url($path)));

    $this->actingAs($owner)
        ->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->create('new.webp', 100, 'image/webp')])
        ->assertRedirect();

    Storage::disk(config('filesystems.default'))->assertMissing($path);

    $this->actingAs($owner)
        ->delete(route('organizations.logo.destroy', $organization))
        ->assertRedirect(route('organizations.edit', $organization));

    expect($organization->fresh()->logo_path)->toBeNull();
    expect(Storage::disk(config('filesystems.default'))->allFiles('organization-logos'))->toBe([]);
});

test('logos must be small images and only people who can update the organization may change them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($owner, 'Owner');
    $organization->addMember($member, 'Member');

    $this->actingAs($owner)
        ->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('logo');

    $this->actingAs($owner)
        ->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->create('huge.png', 3 * 1024, 'image/png')])
        ->assertSessionHasErrors('logo');

    $this->actingAs($member)
        ->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png')])
        ->assertForbidden();

    expect($organization->fresh()->logo_path)->toBeNull();
});
