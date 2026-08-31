<?php

namespace Database\Factories;

use App\Post;
use App\PostComment;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\PostComment>
 */
class PostCommentFactory extends Factory
{
    protected $model = PostComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'comment_text' => fake()->realText(60),
            'like_count' => 0,
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }
}
