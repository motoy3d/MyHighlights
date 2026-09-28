<?php

namespace Tests\Feature\Api;

use App\Jobs\ScheduleNotificationJob;
use App\Schedule;
use App\ScheduleComment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->schedule = Schedule::factory()->create(['team_id' => $this->team->id]);
    }

    private function makeComment(?User $author = null): ScheduleComment
    {
        $author ??= $this->user;

        return ScheduleComment::create([
            'schedule_id' => $this->schedule->id,
            'user_id' => $author->id,
            'comment_text' => '既存コメント',
            'created_id' => $author->id,
            'updated_id' => $author->id,
        ]);
    }

    public function test_予定のコメント一覧を取得できる(): void
    {
        $this->makeComment();
        $this->makeComment();

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/schedule_comments/' . $this->schedule->id)
            ->assertStatus(200);

        $this->assertCount(2, $response->json());
    }

    public function test_予定にコメントできる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedule_comments/' . $this->schedule->id, [
                'schedule_id' => $this->schedule->id,
                'comment_text' => '参加します',
            ])->assertStatus(200);

        $this->assertDatabaseHas('schedule_comments', [
            'schedule_id' => $this->schedule->id,
            'user_id' => $this->user->id,
            'comment_text' => '参加します',
        ]);
    }

    public function test_通知フラグがtrueのとき通知ジョブが積まれる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedule_comments/' . $this->schedule->id, [
                'schedule_id' => $this->schedule->id,
                'comment_text' => '通知するコメント',
                'comment_notification_flg' => 'true',
            ])->assertStatus(200);

        Queue::assertPushed(ScheduleNotificationJob::class);
    }

    public function test_他チームの予定にはコメントできない(): void
    {
        $otherSchedule = Schedule::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedule_comments/' . $otherSchedule->id, [
                'schedule_id' => $otherSchedule->id, 'comment_text' => 'x',
            ])->assertStatus(404);
    }

    public function test_自分のコメントを削除できる(): void
    {
        $comment = $this->makeComment();

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson("/api/schedule_comments/{$this->schedule->id}/{$comment->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('schedule_comments', ['id' => $comment->id]);
    }

    public function test_他人のコメントは削除できない(): void
    {
        $comment = $this->makeComment(User::factory()->create());

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson("/api/schedule_comments/{$this->schedule->id}/{$comment->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('schedule_comments', ['id' => $comment->id]);
    }
}
