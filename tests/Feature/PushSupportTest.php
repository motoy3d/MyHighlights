<?php

namespace Tests\Feature;

use App\Mail\PostNotification;
use App\Support\PushRollout;
use App\Support\ScheduleChange;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #110 の小さな部品(予定の変更判定・段階的な公開・通知メールのリンク)。
 */
class PushSupportTest extends TestCase
{
    use RefreshDatabase;

    private function db(array $overrides = []): array
    {
        // DBから読んだ形
        return array_merge([
            'schedule_date' => '2026-09-27',
            'time_from' => '09:30:00',
            'time_to' => null,
            'allday_flg' => 0,
            'title' => '練習試合',
            'content' => 'メモ',
        ], $overrides);
    }

    private function request(array $overrides = []): array
    {
        // 画面から届いた値をコントローラが代入した後の形
        return array_merge([
            'schedule_date' => '2026-09-27',
            'time_from' => '09:30',
            'time_to' => null,
            'allday_flg' => false,
            'title' => '練習試合 ',
            'content' => 'メモを変えた',
        ], $overrides);
    }

    public function test_形の違いだけでは変更とみなさない(): void
    {
        $this->assertFalse(ScheduleChange::isSignificant($this->db(), $this->request()));
        $this->assertFalse(ScheduleChange::isSignificant(
            $this->db(['time_from' => '09:05:00']), $this->request(['time_from' => '9:05'])));
        $this->assertFalse(ScheduleChange::isSignificant(
            $this->db(['allday_flg' => 1]), $this->request(['allday_flg' => true])));
        $this->assertFalse(ScheduleChange::isSignificant(
            $this->db(['time_to' => null]), $this->request(['time_to' => ''])));
    }

    public function test_日付_時刻_終日_タイトルの変更は変更とみなす(): void
    {
        $this->assertTrue(ScheduleChange::isSignificant($this->db(), $this->request(['schedule_date' => '2026-09-28'])));
        $this->assertTrue(ScheduleChange::isSignificant($this->db(), $this->request(['time_from' => '10:00'])));
        $this->assertTrue(ScheduleChange::isSignificant($this->db(), $this->request(['time_to' => '12:00'])));
        $this->assertTrue(ScheduleChange::isSignificant($this->db(), $this->request(['allday_flg' => true])));
        $this->assertTrue(ScheduleChange::isSignificant($this->db(), $this->request(['title' => '【中止】練習試合'])));
    }

    public function test_文字列のfalseを真にしない(): void
    {
        $this->assertFalse(ScheduleChange::normalizeBool('false'));
        $this->assertFalse(ScheduleChange::normalizeBool('0'));
        $this->assertTrue(ScheduleChange::normalizeBool('true'));
        $this->assertTrue(ScheduleChange::normalizeBool(1));
    }

    public function test_段階的な公開の判定(): void
    {
        $user = User::factory()->make(['email' => 'Coach@Example.com']);

        config(['tsubasa.push_enabled_emails' => '']);
        $this->assertFalse(PushRollout::isEnabledFor($user));
        $this->assertTrue(PushRollout::isDisabledForEveryone());

        config(['tsubasa.push_enabled_emails' => '*']);
        $this->assertTrue(PushRollout::isEnabledFor($user));
        $this->assertFalse(PushRollout::isDisabledForEveryone());

        config(['tsubasa.push_enabled_emails' => ' a@example.com , coach@example.COM ']);
        $this->assertTrue(PushRollout::isEnabledFor($user));

        config(['tsubasa.push_enabled_emails' => 'a@example.com']);
        $this->assertFalse(PushRollout::isEnabledFor($user));
        $this->assertFalse(PushRollout::isEnabledFor(null));
    }

    public function test_通知メールに投稿へのリンクが入る(): void
    {
        $team = Team::factory()->create();
        $link = 'https://tsubasa.example.com/home?launcher=true&team=' . $team->id . '&post=5';

        $body = (new PostNotification(null, '件名', '本文', $team, $link))->render();

        // テキストメールなので & がエスケープされていないこと
        $this->assertStringContainsString($link, $body);
        $this->assertStringContainsString('本文', $body);
    }

    public function test_リンクが無ければ従来どおり(): void
    {
        $team = Team::factory()->create();

        $body = (new PostNotification(null, '件名', '本文', $team))->render();

        $this->assertStringNotContainsString('アプリで開く', $body);
        $this->assertStringContainsString((string) config('app.url'), $body);
    }
}
