<?php

use App\Models\IssueType;
use App\Models\Organization;
use Database\Seeders\IssueTypeSeeder;

test('the seeded types are unscoped templates and every organization gets a copy', function () {
    new IssueTypeSeeder()->run();

    $templates = IssueType::whereNull('organization_id')->pluck('name');

    expect($templates)->toHaveCount(3);

    $organization = Organization::factory()->create();

    expect(IssueType::where('organization_id', $organization->id)->pluck('name')->sort()->values()->all())
        ->toBe($templates->sort()->values()->all());
})->note('Organizations rename and retire their own types, so each gets a copy rather than a shared row.');

test('a type renamed in one organization leaves the other alone', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    $type = IssueType::where('organization_id', $mine->id)->first();
    $original = $type->name;
    $type->update(['name' => 'Defect']);

    expect(IssueType::where('organization_id', $theirs->id)->pluck('name'))->toContain($original);
});

test('archived types are excluded from the active scope', function () {
    $organization = Organization::factory()->create();
    $type = IssueType::where('organization_id', $organization->id)->first();

    $type->update(['archived_at' => now()]);

    expect(IssueType::active()->where('organization_id', $organization->id)->pluck('id'))
        ->not->toContain($type->id);
});
