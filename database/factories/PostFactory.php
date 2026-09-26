<?php

namespace Database\Factories;

use App\Post;
use App\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => fake()->realText(20),
            'content' => fake()->realText(120),
            'category_id' => null,
            'questionnaire_id' => null,
            'notification_flg' => 0,
            'comment_count' => 0,
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }
}
