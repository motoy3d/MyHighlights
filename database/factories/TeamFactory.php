<?php

namespace Database\Factories;

use App\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'color' => fake()->hexColor(),
            'plan_id' => 0,
            'ical_id' => (string) Str::uuid(),
            'blog_rss' => null,
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }
}
