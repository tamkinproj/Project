<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Social\Enums\PostVisibility;
use App\Modules\Social\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'visibility' => PostVisibility::Public,
        ];
    }

    public function visibility(PostVisibility $visibility): static
    {
        return $this->state(fn () => ['visibility' => $visibility]);
    }
}
