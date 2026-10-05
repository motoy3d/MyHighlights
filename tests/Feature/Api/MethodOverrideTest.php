<?php

namespace Tests\Feature\Api;

use App\Post;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPAは投稿・予定の更新時、multipart/form-dataを送るために
 * POST + X-HTTP-Method-Override: PUT でPUTルートを呼んでいる。
 * この経路が移行後も通ることを確認する。
 */
class MethodOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
    }

    public function test_投稿の更新がPOSTとメソッドオーバーライドで通る(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts/' . $post->id, [
                'title' => 'オーバーライド更新',
                'contents' => '本文',
                'notification_flg' => 0,
            ], ['X-HTTP-Method-Override' => 'PUT'])
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id, 'title' => 'オーバーライド更新',
        ]);
    }

    public function test_予定の更新がPOSTとメソッドオーバーライドで通る(): void
    {
        $schedule = Schedule::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/schedules/' . $schedule->id, [
                'schedule_date' => '2026-11-03',
                'title' => 'オーバーライド更新',
                'allday_flg' => 'false',
                'time_from' => '10:00',
                'time_to' => '11:00',
                'contents' => '内容',
                'notification_flg' => 'false',
            ], ['X-HTTP-Method-Override' => 'PUT'])
            ->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id, 'title' => 'オーバーライド更新',
        ]);
    }

    public function test_オーバーライドなしのPOSTは405になる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/posts/' . $post->id, ['title' => 'x'])
            ->assertStatus(405);
    }
}
