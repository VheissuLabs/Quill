<?php

use App\Enums\ProjectVisibility;
use App\Models\Document;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function documentWorkspace(string $role = 'Admin'): array
{
    Storage::fake();
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $project = Project::factory()->for($organization)->create();

    return [$organization, $user, $project, Issue::factory()->for($project)->create()];
}

test('a member uploads a screenshot to an issue and sees it on the issue', function () {
    [$organization, $user, , $issue] = documentWorkspace('Member');

    $this->actingAs($user)
        ->post(route('issues.documents.store', [$organization, $issue]), ['file' => UploadedFile::fake()->image('broken-login.png')])
        ->assertRedirect();

    $document = $issue->documents()->sole();

    expect($document)
        ->name->toBe('broken-login.png')
        ->organization_id->toBe($organization->id)
        ->user_id->toBe($user->id);
    Storage::assertExists($document->path);

    $this->actingAs($user)
        ->get(route('issues.show', [$organization, $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue.documents.0.name', 'broken-login.png')
            ->where('issue.documents.0.isImage', true));
});

test('a project manager uploads a PDF to a project', function () {
    [$organization, $user, $project] = documentWorkspace();

    $this->actingAs($user)
        ->post(route('projects.documents.store', [$organization, $project]), ['file' => UploadedFile::fake()->create('statement-of-work.pdf', 300, 'application/pdf')])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('projects.edit', [$organization, $project]))
        ->assertInertia(fn (Assert $page) => $page->where('documents.0.name', 'statement-of-work.pdf'));
});

test('only PDFs and images up to 20 MB are accepted', function (UploadedFile $file, string $message) {
    [$organization, $user, , $issue] = documentWorkspace();

    $this->actingAs($user)
        ->post(route('issues.documents.store', [$organization, $issue]), ['file' => $file])
        ->assertSessionHasErrors(['file' => $message]);

    expect(Document::count())->toBe(0);
})->with([
    'a spreadsheet' => fn () => [UploadedFile::fake()->create('rates.xlsx', 10), 'Upload a PDF or an image.'],
    'a huge PDF' => fn () => [UploadedFile::fake()->create('scan.pdf', 20481, 'application/pdf'), 'Files can be up to 20 MB.'],
]);

test('a document opens for members who can see its record', function () {
    [$organization, $user, , $issue] = documentWorkspace('Member');
    $document = Document::storeFor($issue, $organization->id, UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'), $user);

    $this->actingAs($user)
        ->get(route('documents.show', [$organization, $document]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('documents stay private to the organization, even on a public project', function () {
    [$organization, $user, $project] = documentWorkspace();
    $project->update(['visibility' => ProjectVisibility::Public]);
    $document = Document::storeFor($project, $organization->id, UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'), $user);
    $outsider = User::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherOrganization->addMember($outsider, 'Admin');

    $this->actingAs($outsider)
        ->get(route('documents.show', [$otherOrganization, $document]))
        ->assertForbidden();
    $this->actingAs($outsider)
        ->delete(route('documents.destroy', [$otherOrganization, $document]))
        ->assertForbidden();
});

test('deleting a document removes its file from storage', function () {
    [$organization, $user, , $issue] = documentWorkspace();
    $document = Document::storeFor($issue, $organization->id, UploadedFile::fake()->image('shot.png'), $user);

    $this->actingAs($user)
        ->delete(route('documents.destroy', [$organization, $document]))
        ->assertRedirect();

    expect(Document::count())->toBe(0);
    Storage::assertMissing($document->path);
});

test('deleting an issue removes its documents and their files', function () {
    [$organization, $user, , $issue] = documentWorkspace();
    $document = Document::storeFor($issue, $organization->id, UploadedFile::fake()->image('shot.png'), $user);

    $issue->delete();

    expect(Document::count())->toBe(0);
    Storage::assertMissing($document->path);
});

test('deleting a project removes the documents on it and on its issues', function () {
    [$organization, $user, $project, $issue] = documentWorkspace();
    $onProject = Document::storeFor($project, $organization->id, UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'), $user);
    $onIssue = Document::storeFor($issue, $organization->id, UploadedFile::fake()->image('shot.png'), $user);

    $project->delete();

    expect(Document::count())->toBe(0);
    Storage::assertMissing($onProject->path);
    Storage::assertMissing($onIssue->path);
});

test('search finds documents by name and opens the file', function () {
    [$organization, $user, $project] = documentWorkspace();
    $document = Document::storeFor($project, $organization->id, UploadedFile::fake()->create('Quarterly roadmap.pdf', 10, 'application/pdf'), $user);

    $this->actingAs($user)
        ->getJson(route('palette.search', ['current_organization' => $organization, 'q' => 'roadmap']))
        ->assertJsonFragment([
            'type' => 'document',
            'title' => 'Quarterly roadmap.pdf',
            'subtitle' => $project->name,
            'url' => route('documents.show', [$organization, $document]),
        ]);
});
