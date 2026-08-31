<?php

namespace Database\Factories;

use App\Category;
use App\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->randomElement(['練習', '試合', '大会', 'その他']),
            'order_no' => fake()->numberBetween(1, 10),
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }
}
