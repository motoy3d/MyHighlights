<?php

namespace Database\Factories;

use App\Member;
use App\Team;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'name_kana' => fake()->kanaName(),
            'type' => '選手',
            'admin_flg' => 0,
            'backno' => fake()->numberBetween(1, 99),
            'birthday' => fake()->dateTimeBetween('-40 years', '-10 years')->format('Y-m-d'),
            'prof_img_filename' => 'noimage.png',
            'withdrawal_date' => null,
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }

    /**
     * チーム管理者。
     */
    public function admin(): static
    {
        return $this->state(fn () => ['admin_flg' => 1]);
    }
}
