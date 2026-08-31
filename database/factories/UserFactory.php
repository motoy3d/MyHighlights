<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * 全ファクトリで共有するパスワード。都度ハッシュ化するとseedが遅くなるため一度だけ計算する。
     */
    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'name_kana' => fake()->kanaName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'mail_notification_flg' => 1,
            'line_notification_flg' => 0,
            'withdrawal_date' => null,
            'remember_token' => Str::random(10),
            'created_id' => 1,
            'updated_id' => 1,
        ];
    }

    /**
     * 退会済みユーザ。ログインできないことの確認に使う。
     */
    public function withdrawn(): static
    {
        return $this->state(fn () => ['withdrawal_date' => now()->subMonth()->toDateString()]);
    }
}
