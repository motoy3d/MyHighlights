<?php

namespace Tests\Feature;

use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * iCal出力のテスト。
 *
 * eluceo/ical 0.14 -> 2.x でAPIが全面的に変わったため、
 * 出力内容が従来と同じであることを確認する。
 */
class ICalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->team->forceFill(['ical_id' => 'ical-test-0001', 'name' => 'つばさ'])->save();
    }

    private function fetchIcal(): string
    {
        return $this->get('/ical/ical-test-0001')->assertStatus(200)->getContent();
    }

    public function test_カレンダー名とPRODIDが出力される(): void
    {
        $body = $this->fetchIcal();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('PRODID:tsubasa.smartj.mobi', $body);
        $this->assertStringContainsString('X-WR-CALNAME:つばさ', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_時刻ありの予定はタイムゾーン付きで出力される(): void
    {
        Schedule::factory()->create([
            'team_id' => $this->team->id,
            'title' => '練習試合',
            'schedule_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'time_from' => '09:30:00',
            'time_to' => '12:00:00',
        ]);

        $body = $this->fetchIcal();
        $date = now()->startOfMonth()->addDays(4)->format('Ymd');

        $this->assertStringContainsString('SUMMARY:練習試合', $body);
        $this->assertStringContainsString("DTSTART;TZID=Asia/Tokyo:{$date}T093000", $body);
        $this->assertStringContainsString("DTEND;TZID=Asia/Tokyo:{$date}T120000", $body);
        // VTIMEZONEが添えられている
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $body);
        $this->assertStringContainsString('TZID:Asia/Tokyo', $body);
    }

    public function test_終日予定はVALUE_DATEで出力される(): void
    {
        Schedule::factory()->allDay()->create([
            'team_id' => $this->team->id,
            'title' => '合宿',
            'schedule_date' => now()->startOfMonth()->addDays(11)->toDateString(),
        ]);

        $body = $this->fetchIcal();
        $date = now()->startOfMonth()->addDays(11)->format('Ymd');

        $this->assertStringContainsString('SUMMARY:合宿', $body);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:{$date}", $body);
    }

    public function test_開始時刻のみの予定は終了時刻が開始時刻と同じになる(): void
    {
        Schedule::factory()->startOnly()->create([
            'team_id' => $this->team->id,
            'title' => 'ミーティング',
            'schedule_date' => now()->startOfMonth()->addDays(19)->toDateString(),
        ]);

        $body = $this->fetchIcal();
        $date = now()->startOfMonth()->addDays(19)->format('Ymd');

        $this->assertStringContainsString("DTSTART;TZID=Asia/Tokyo:{$date}T180000", $body);
        $this->assertStringContainsString("DTEND;TZID=Asia/Tokyo:{$date}T180000", $body);
    }

    public function test_他チームの予定は含まれない(): void
    {
        Schedule::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'title' => '他チームの予定',
            'schedule_date' => now()->toDateString(),
        ]);

        $this->assertStringNotContainsString('他チームの予定', $this->fetchIcal());
    }

    public function test_ファイル名付きで添付として返る(): void
    {
        $this->get('/ical/ical-test-0001')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="つばさ.ics"');
    }

    public function test_存在しないical_idは404(): void
    {
        $this->get('/ical/does-not-exist')->assertStatus(404);
    }

    public function test_認証なしで購読できる(): void
    {
        // カレンダーアプリから購読するため、このURLは認証不要
        $this->assertGuest();
        $this->get('/ical/ical-test-0001')->assertStatus(200);
    }

    public function test_購読URLをAPIで取得できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/ical/config')
            ->assertStatus(200)
            ->assertJsonPath('ical_url', config('app.url') . '/ical/ical-test-0001');
    }
}
