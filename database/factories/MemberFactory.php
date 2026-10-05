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

    /** 本番の members.type に実際に入っている値 */
    public const TYPE_PLAYER = '1';   // 選手
    public const TYPE_STAFF  = '2';   // 監督・コーチ
    public const TYPE_FAMILY = '3';   // 家族

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
            // members.type は string(10) だが、本番に入っているのは
            // '1'(選手) / '2'(監督・コーチ) / '3'(家族) という数値コード。
            // マイグレーションのコメント「種別(選手,スタッフ,家族)」は
            // 意味を書いたもので、格納値そのものではない。
            // 画面側は `member.type == 1` で絞り込むため、ここに '選手' の
            // ような文字列を入れるとメンバー一覧が常に空になる。
            'type' => self::TYPE_PLAYER,
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

    /** 監督・コーチ */
    public function staff(): static
    {
        return $this->state(fn () => ['type' => self::TYPE_STAFF, 'backno' => null]);
    }

    /** 家族 */
    public function family(): static
    {
        return $this->state(fn () => ['type' => self::TYPE_FAMILY, 'backno' => null]);
    }
}
