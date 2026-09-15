<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Discussion on about two thirds of the requests: threads with replies, the odd mention, and
 * internal notes only organization members can read.
 */
class CommentSeeder extends Seeder
{
    public function run(): void
    {
        Feature::with('project.organization.members')->get()->each(function (Feature $feature) {
            if (fake()->boolean(35)) {
                return;
            }

            $members = $feature->project->organization->members;

            Comment::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($feature)
                ->sequence(fn () => [
                    'user_id' => $members->random()->id,
                    'body' => fake()->sentences(fake()->numberBetween(1, 3), true),
                    'is_internal' => fake()->boolean(20),
                    'created_at' => fake()->dateTimeBetween($feature->created_at, 'now'),
                ])
                ->create()
                ->each(function (Comment $comment) use ($members) {
                    if (fake()->boolean(50)) {
                        $this->reply($comment, $members);
                    }
                });
        });
    }

    /** @param  Collection<int, User>  $members */
    protected function reply(Comment $parent, $members): void
    {
        $author = $members->random();
        $mentioned = fake()->boolean(60) ? $parent->author : null;

        $reply = Comment::factory()->replyTo($parent)->create([
            'user_id' => $author->id,
            'body' => ($mentioned ? "@{$mentioned->name} " : '').fake()->sentence(),
            'created_at' => fake()->dateTimeBetween($parent->created_at, 'now'),
        ]);

        if ($mentioned) {
            $reply->mentions()->attach($mentioned->id);
        }
    }
}
