<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'commentable_id' => Feature::factory(),
            'commentable_type' => (new Feature)->getMorphClass(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_id' => $parent->commentable_id,
            'commentable_type' => $parent->commentable_type,
            'parent_id' => $parent->id,
            'is_internal' => $parent->is_internal,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }
}
