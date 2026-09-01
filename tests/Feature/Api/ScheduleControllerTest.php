<?php

namespace Tests\Feature\Api;

use App\Category;
use App\Holiday;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
    }

    private function thisMonth(): string
    {
        return now()->format('Ym');
    }

    // index --------------------------------------------------------

    public function test_指定月の前後の予定が返る(): void
    {
        Schedule::factory()->count(3)->create([
            'team_id' => $this->team->id,
            'schedule_date' => now()->startOfMonth()->addDays(3)->toDateString(),
        ]);
        Schedule::factory()->create(['team_id' => Team::factory()->create()->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/schedules?month=' . $this->thisMonth())
            ->assertStatus(200);

        $this->assertCount(3, $response->json('schedules'));
    }

    public function test_祝日も一緒に返る(): void
    {
        Holiday::create([
            'holiday_date' => now()->startOfMonth()->toDateString(),
            'name' => 'テスト祝日',
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/schedules?month=' . $this->thisMonth())
            ->assertStatus(200);

        $this->assertCount(1, $response->json('holidays'));
    }

    public function test_monthパラメータが無いと500になる(): void
    {
        // コントローラ側に //TODO validate と書かれている既知の挙動。
        // 移行によるものではないため、現状の仕様として固定しておく。
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/schedules')
            ->assertStatus(500);
    }

    // create --------------------------------------------------------

    public function test_予定登録画面用のカテゴリ一覧が返る(): void
    {
        Category::factory()->count(2)->create(['team_id' => $this->team->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/schedules/create')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('categories'));
    }

    // store ---------------------------------------------------------

    public function test_予定を登録できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedules', [
                'schedule_date' => '2026-09-15',
                'title' => '練習試合',
                'allday_flg' => 'false',
                'time_from' => '09:00',
                'time_to' => '12:00',
                'contents' => '集合は8時半',
                'notification_flg' => 'false',
            ])->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'team_id' => $this->team->id,
            'schedule_date' => '2026-09-15',
            'title' => '練習試合',
            'allday_flg' => 0,
            'created_id' => $this->user->id,
        ]);
    }

    public function test_終日予定を登録できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/schedules', [
                'schedule_date' => '2026-09-20',
                'title' => '合宿',
                'allday_flg' => 'true',
                'notification_flg' => 'false',
            ])->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'title' => '合宿', 'allday_flg' => 1, 'time_from' => null,
        ]);
    }

    // update --------------------------------------------------------

    public function test_予定を更新できる(): void
    {
        $schedule = Schedule::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, [
                'schedule_date' => '2026-10-01',
                'title' => '更新後のタイトル',
                'allday_flg' => 'false',
                'time_from' => '13:00',
                'time_to' => 'null',
                'contents' => '内容更新',
                'notification_flg' => 'false',
            ])->assertStatus(200);

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => '更新後のタイトル',
            'schedule_date' => '2026-10-01',
            'time_to' => null,
            'updated_id' => $this->user->id,
        ]);
    }

    public function test_他チームの予定は更新できない(): void
    {
        $schedule = Schedule::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/schedules/' . $schedule->id, ['title' => 'x'])
            ->assertStatus(404);
    }

    // destroy -------------------------------------------------------

    public function test_予定を削除できる(): void
    {
        $schedule = Schedule::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(200)
            ->assertJson(['deleted_count' => 1]);

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_他チームの予定は削除できない(): void
    {
        $schedule = Schedule::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    }
}
