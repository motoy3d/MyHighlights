<?php

namespace Database\Factories;

use App\Schedule;
use App\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    /**
     * schedulesは(team_id, schedule_date, title)にユニーク制約があるため、
     * 連番を付けてタイトルが衝突しないようにする。
     */
    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'schedule_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'title' => fake()->randomElement(['練習', '練習試合', 'リーグ戦', 'ミーティング'])
                . ' #' . ++self::$sequence,
            'allday_flg' => 0,
            'time_from' => '09:30:00',
            'time_to' => '12:00:00',
            'content' => fake()->realText(40),
            'notification_flg' => 0,
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }

    /**
     * 終日予定。iCalではVALUE=DATEで出力される。
     */
    public function allDay(): static
    {
        return $this->state(fn () => [
            'allday_flg' => 1,
            'time_from' => null,
            'time_to' => null,
        ]);
    }

    /**
     * 開始時刻のみの予定。iCalではDTENDがDTSTARTと同じになる。
     */
    public function startOnly(): static
    {
        return $this->state(fn () => [
            'allday_flg' => 0,
            'time_from' => '18:00:00',
            'time_to' => null,
        ]);
    }
}
