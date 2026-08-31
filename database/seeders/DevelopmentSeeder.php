<?php

namespace Database\Seeders;

use App\Category;
use App\Member;
use App\Post;
use App\PostComment;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * 開発・動作確認用のデータを投入する。
 *
 * 移行後の動作確認(ログイン、タイムライン、予定、iCal出力)を
 * そのまま再現できる最小限のデータセット。
 *
 *   php artisan migrate:fresh --seed
 *
 * ログイン: test@example.com / password
 */
class DevelopmentSeeder extends Seeder
{
    /**
     * 全ユーザ共通のログインパスワード。
     */
    public const PASSWORD = 'password';

    /**
     * iCal購読URLの確認に使う固定ID。
     */
    public const ICAL_ID = 'ical-dev-0001';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeederは本番環境では実行できません。');
        }

        $team = Team::factory()->create([
            'name' => '横浜SCつばさ',
            'color' => '#e60012',
            'ical_id' => self::ICAL_ID,
        ]);

        // 「他チームのデータは見えない」系の確認用にもう1チーム用意する
        $otherTeam = Team::factory()->create([
            'name' => '別チーム',
            'color' => '#0068b7',
        ]);

        $admin = User::factory()->create([
            'name' => 'テスト太郎',
            'name_kana' => 'テストタロウ',
            'email' => 'test@example.com',
        ]);
        Member::factory()->admin()->create([
            'user_id' => $admin->id,
            'team_id' => $team->id,
            'name' => $admin->name,
            'name_kana' => $admin->name_kana,
            'backno' => 10,
        ]);

        $otherUser = User::factory()->create([
            'name' => 'テスト次郎',
            'name_kana' => 'テストジロウ',
            'email' => 'test2@example.com',
        ]);
        Member::factory()->create([
            'user_id' => $otherUser->id,
            'team_id' => $otherTeam->id,
            'name' => $otherUser->name,
            'name_kana' => $otherUser->name_kana,
        ]);

        // 同じチームの一般メンバー
        User::factory()
            ->count(3)
            ->create()
            ->each(fn (User $user) => Member::factory()->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
                'name' => $user->name,
                'name_kana' => $user->name_kana,
            ]));

        Category::factory()->count(4)->create(['team_id' => $team->id]);

        // iCal出力の3パターンを網羅する
        Schedule::factory()->create([
            'team_id' => $team->id,
            'title' => '練習試合(時刻あり)',
            'schedule_date' => now()->startOfMonth()->addDays(4)->toDateString(),
        ]);
        Schedule::factory()->allDay()->create([
            'team_id' => $team->id,
            'title' => '終日イベント',
            'schedule_date' => now()->startOfMonth()->addDays(11)->toDateString(),
        ]);
        Schedule::factory()->startOnly()->create([
            'team_id' => $team->id,
            'title' => '開始時刻のみ',
            'schedule_date' => now()->startOfMonth()->addDays(19)->toDateString(),
        ]);
        Schedule::factory()->count(5)->create(['team_id' => $team->id]);

        Post::factory()
            ->count(12)
            ->create(['team_id' => $team->id, 'created_id' => $admin->id])
            ->each(function (Post $post) use ($admin) {
                $comments = PostComment::factory()->count(2)->create([
                    'post_id' => $post->id,
                    'user_id' => $admin->id,
                    'created_id' => $admin->id,
                ]);
                $post->update(['comment_count' => $comments->count()]);
            });

        $this->command?->info('ログイン: test@example.com / ' . self::PASSWORD);
        $this->command?->info('iCal   : /ical/' . self::ICAL_ID);
    }
}
