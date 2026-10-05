<?php

namespace Tests\Feature;

use App\Jobs\PostNotificationJob;
use App\Jobs\PushNotificationJob;
use App\Post;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * どの操作でプッシュ通知のジョブが積まれるか(#110)。
 */
class PushDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        [$this->team, $this->user] = $this->makeTeamWithUser();
    }

    private function makeSchedule(array $attributes = []): Schedule
    {
        return Schedule::factory()->create(array_merge([
            'team_id' => $this->team->id,
            'schedule_date' => now('Asia/Tokyo')->addDays(7)->toDateString(),
            'title' => '練習試合',
            'allday_flg' => 0,
            'time_from' => '09:30:00',
            'time_to' => '12:00:00',
            'content' => '集合は9時',
        ], $attributes));
    }

    /**
     * 画面(予定の編集)が送ってくる形。値は予定の今の内容と同じにしておき、
     * 変えたい項目だけ $overrides で上書きする。
     */
    private function updatePayload(Schedule $schedule, array $overrides = []): array
    {
        return array_merge([
            'schedule_date' => $schedule->schedule_date,
            'title' => $schedule->title,
            'allday_flg' => $schedule->allday_flg ? 'true' : 'false',
            // 画面は秒なしで送ってくる
            'time_from' => $schedule->time_from ? substr($schedule->time_from, 0, 5) : 'null',
            'time_to' => $schedule->time_to ? substr($schedule->time_to, 0, 5) : 'null',
            'category_id' => null,
            'contents' => $schedule->content,
            'notification_flg' => 'false',
            'notify_change' => 'true',
        ], $overrides);
    }

    private function assertScheduleChangePushed(Schedule $schedule): void
    {
        Queue::assertPushed(PushNotificationJob::class, fn (PushNotificationJob $job) =>
            $job->type === PushNotificationJob::TYPE_SCHEDULE_CHANGE
            && $job->scheduleId === $schedule->id
            && $job->teamId === $this->team->id
            && $job->actorId === $this->user->id);
    }

    // 投稿 ----------------------------------------------------------------

    public function test_新しい投稿でプッシュのジョブが積まれる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/posts', ['title' => '通知あり', 'contents' => '本文', 'notification_flg' => 0])
            ->assertStatus(200);

        $post = Post::where('title', '通知あり')->firstOrFail();
        Queue::assertPushed(PushNotificationJob::class, fn (PushNotificationJob $job) =>
            $job->type === PushNotificationJob::TYPE_NEW_POST
            && $job->postId === $post->id
            && $job->teamId === $this->team->id
            && $job->actorId === $this->user->id);
        // メールも従来どおり積まれる
        Queue::assertPushed(PostNotificationJob::class);
    }

    public function test_投稿へのコメントでベルがオンならプッシュのジョブが積まれる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $post->id, [
                'post_id' => $post->id,
                'comment_text' => 'ありがとうございます',
                'comment_notification_flg' => 'true',
            ])->assertStatus(200);

        Queue::assertPushed(PushNotificationJob::class, fn (PushNotificationJob $job) =>
            $job->type === PushNotificationJob::TYPE_POST_COMMENT
            && $job->postId === $post->id
            && $job->commentId !== null);
    }

    public function test_投稿へのコメントでベルがオフならプッシュのジョブは積まれない(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $post->id, [
                'post_id' => $post->id,
                'comment_text' => 'ありがとうございます',
                'comment_notification_flg' => 'false',
            ])->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    // 予定へのコメント ----------------------------------------------------------

    public function test_予定へのコメントでベルがオンならプッシュのジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedule_comments/' . $schedule->id, [
                'schedule_id' => $schedule->id,
                'comment_text' => '参加します',
                'comment_notification_flg' => 'true',
            ])->assertStatus(200);

        Queue::assertPushed(PushNotificationJob::class, fn (PushNotificationJob $job) =>
            $job->type === PushNotificationJob::TYPE_SCHEDULE_COMMENT
            && $job->scheduleId === $schedule->id
            && $job->commentId !== null);
    }

    public function test_予定へのコメントでベルがオフならプッシュのジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedule_comments/' . $schedule->id, [
                'schedule_id' => $schedule->id,
                'comment_text' => '参加します',
            ])->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    // 予定の変更 ------------------------------------------------------------

    public function test_日付を変えて通知オンならジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'schedule_date' => now('Asia/Tokyo')->addDays(8)->toDateString(),
            ]))->assertStatus(200);

        $this->assertScheduleChangePushed($schedule);
    }

    public function test_開始時刻を変えるとジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'time_from' => '10:00',
            ]))->assertStatus(200);

        $this->assertScheduleChangePushed($schedule);
    }

    public function test_終了時刻を消すとジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'time_to' => 'null',
            ]))->assertStatus(200);

        $this->assertScheduleChangePushed($schedule);
    }

    public function test_終日に変えるとジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'allday_flg' => 'true',
            ]))->assertStatus(200);

        $this->assertScheduleChangePushed($schedule);
    }

    public function test_タイトルを変えるとジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'title' => '【中止】練習試合',
            ]))->assertStatus(200);

        $this->assertScheduleChangePushed($schedule);
    }

    public function test_メモ欄だけの変更ではジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule();

        // 時刻は秒なし('09:30')で送られ、DBは秒あり('09:30:00')。同じ時刻として扱う
        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'contents' => 'メモを書き換えた',
            ]))->assertStatus(200);

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id, 'content' => 'メモを書き換えた']);
        Queue::assertNotPushed(PushNotificationJob::class);
    }

    public function test_タイトルの前後の空白だけの変更ではジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'title' => ' 練習試合 ',
            ]))->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    public function test_通知スイッチがオフならジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule();

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $this->updatePayload($schedule, [
                'schedule_date' => now('Asia/Tokyo')->addDays(8)->toDateString(),
                'notify_change' => 'false',
            ]))->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    public function test_notify_changeを送らない古い画面ではジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule();
        $payload = $this->updatePayload($schedule, [
            'schedule_date' => now('Asia/Tokyo')->addDays(8)->toDateString(),
        ]);
        unset($payload['notify_change']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, $payload)
            ->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    // 予定の削除 ------------------------------------------------------------

    public function test_今後の予定を削除するとジョブが積まれる(): void
    {
        $date = now('Asia/Tokyo')->addDays(3)->toDateString();
        $schedule = $this->makeSchedule(['schedule_date' => $date, 'title' => '練習']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(200);

        Queue::assertPushed(PushNotificationJob::class, fn (PushNotificationJob $job) =>
            $job->type === PushNotificationJob::TYPE_SCHEDULE_DELETED
            && $job->scheduleId === $schedule->id
            && $job->teamId === $this->team->id
            && $job->snapshot['title'] === '練習'
            && $job->snapshot['schedule_date'] === $date);
    }

    public function test_今日の予定を削除してもジョブが積まれる(): void
    {
        $schedule = $this->makeSchedule(['schedule_date' => now('Asia/Tokyo')->toDateString()]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(200);

        Queue::assertPushed(PushNotificationJob::class);
    }

    public function test_過去の予定を削除してもジョブは積まれない(): void
    {
        $schedule = $this->makeSchedule(['schedule_date' => now('Asia/Tokyo')->subDay()->toDateString()]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }

    public function test_予定の新規登録ではジョブは積まれない(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedules', [
                'schedule_date' => now('Asia/Tokyo')->addDays(3)->toDateString(),
                'title' => '新しい予定',
                'allday_flg' => 'true',
                'notification_flg' => 'false',
            ])->assertStatus(200);

        Queue::assertNotPushed(PushNotificationJob::class);
    }
}
