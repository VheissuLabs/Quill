<?php

use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Support\RichText;

test('it keeps the marks the editor produces', function () {
    $html = '<p>A <strong>bold</strong> and <em>italic</em> point</p><ul><li>one</li></ul>';

    expect(RichText::sanitize($html))
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<li>one</li>');
});

test('it throws away script tags and event handlers', function () {
    $html = '<p onclick="steal()">Hello<script>alert(1)</script></p>';

    $clean = RichText::sanitize($html);

    expect($clean)
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->toContain('Hello');
});

test('it refuses javascript urls while keeping ordinary links', function () {
    expect(RichText::sanitize('<p><a href="javascript:alert(1)">tap</a></p>'))
        ->not->toContain('javascript:');

    expect(RichText::sanitize('<p><a href="https://example.com">tap</a></p>'))
        ->toContain('https://example.com');
});

test('it keeps a mention with the person it points at', function () {
    $clean = RichText::sanitize('<p><span data-mention data-id="7">@Grace Hopper</span></p>');

    expect($clean)
        ->toContain('data-mention')
        ->toContain('data-id="7"');

    expect(RichText::toText($clean))->toBe('@Grace Hopper');
});

test('it reads back as plain text with the lines intact', function () {
    $html = '<p>First line</p><p>/status done</p><ul><li>a point</li></ul>';

    expect(RichText::toText($html))->toBe("First line\n/status done\na point");
});

test('plain text written before the editor existed survives a round trip', function () {
    expect(RichText::toText('Just a sentence.'))->toBe('Just a sentence.');
});

test('a description is sanitised wherever it is written', function () {
    $project = Project::factory()
        ->for(Organization::factory())
        ->create(['description' => '<p>Safe<script>alert(1)</script></p>']);

    $feedback = Feedback::factory()
        ->for($project)
        ->create(['description' => '<p onclick="x()">Also safe</p>']);

    $issue = Issue::factory()
        ->for($project)
        ->create(['description' => '<p><a href="javascript:x()">tap</a></p>']);

    expect($project->description)->not->toContain('script')
        ->and($feedback->description)->not->toContain('onclick')
        ->and($issue->description)->not->toContain('javascript:');
});
