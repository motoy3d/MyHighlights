<?php

namespace Tests\Feature;

use App\Member;
use App\Post;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * チームをまたいだ認可のテスト。
 *
 * 対象チームは current_team_id クッキーで決まるが、jsから読むために
 * 暗号化対象外にしてあり、利用者がブラウザ側で書き換えられる。
 * EnsureCurrentTeamIsOwn ミドルウェアで所属チームかを検証し、
 * 不正な値は所属チームの1件目へ是正する。
 */
class CrossTeamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_非所属チームのクッキーでは他チームの投稿は見えない(): void
    {
        [$teamA, $outsider] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        Post::factory()->count(3)->create(['team_id' => $teamB->id]);
        Post::factory()->create(['team_id' => $teamA->id]);

        $response = $this->actingAsTeamMember($outsider, $teamB)
            ->getJson('/api/posts')
            ->assertStatus(200);

        // 所属チームAに是正され、Bの投稿は1件も返らない
        $this->assertCount(1, $response->json('posts.data'));
        $this->assertSame($teamA->id, $response->json('posts.data.0.team_id'));
    }

    public function test_非所属チームのクッキーは所属チームに是正されブラウザにも返る(): void
    {
        [$teamA, $user] = $this->makeTeamWithUser();
        $foreign = Team::factory()->create();

        $this->actingAsTeamMember($user, $foreign)
            ->getJson('/api/teams')
            ->assertStatus(200)
            ->assertJsonPath('id', $teamA->id)
            ->assertPlainCookie('current_team_id', (string) $teamA->id)
            ->assertPlainCookie('current_team_name', $teamA->name);
    }

    public function test_非所属チームの投稿詳細は404になる(): void
    {
        [$teamA, $outsider] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        $post = Post::factory()->create(['team_id' => $teamB->id]);

        $this->actingAsTeamMember($outsider, $teamB)
            ->getJson('/api/posts/' . $post->id)
            ->assertStatus(404);
    }

    public function test_非所属チームには投稿を作成できない(): void
    {
        [$teamA, $outsider] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();

        $this->actingAsTeamMember($outsider, $teamB)
            ->postJson('/api/posts', ['title' => '侵入', 'contents' => '本文', 'notification_flg' => 0])
            ->assertStatus(200);

        // 是正された所属チーム側に作られ、他チームには作られない
        $this->assertDatabaseHas('posts', ['title' => '侵入', 'team_id' => $teamA->id]);
        $this->assertDatabaseMissing('posts', ['title' => '侵入', 'team_id' => $teamB->id]);
    }

    public function test_非所属チームの予定は見えない(): void
    {
        [$teamA, $outsider] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        Schedule::factory()->count(2)->create([
            'team_id' => $teamB->id,
            'schedule_date' => now()->startOfMonth()->addDays(3)->toDateString(),
        ]);

        $response = $this->actingAsTeamMember($outsider, $teamB)
            ->getJson('/api/schedules?month=' . now()->format('Ym'))
            ->assertStatus(200);

        $this->assertCount(0, $response->json('schedules'));
    }

    public function test_別チームの管理者は他チームのメンバーを退会させられない(): void
    {
        // 攻撃者はチームAの管理者。チームBには所属していない。
        [$teamA, $attacker] = $this->makeTeamWithUser(admin: true);
        $teamB = Team::factory()->create();
        $victim = Member::factory()->create([
            'team_id' => $teamB->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($attacker, $teamB)
            ->deleteJson('/api/members/' . $victim->id)
            ->assertStatus(404);

        $this->assertNull($victim->fresh()->withdrawal_date);
    }

    public function test_所属していても管理者でなければ退会させられない(): void
    {
        [$team, $plain] = $this->makeTeamWithUser(admin: false);
        $other = Member::factory()->create([
            'team_id' => $team->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($plain, $team)
            ->deleteJson('/api/members/' . $other->id)
            ->assertStatus(404);

        $this->assertNull($other->fresh()->withdrawal_date);
    }

    public function test_別チームで管理者でも現在のチームで一般なら退会させられない(): void
    {
        // チームAでは管理者、チームBでは一般メンバー
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        Member::factory()->admin()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
        Member::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

        $victim = Member::factory()->create([
            'team_id' => $teamB->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($user, $teamB)
            ->deleteJson('/api/members/' . $victim->id)
            ->assertStatus(404);

        $this->assertNull($victim->fresh()->withdrawal_date);
    }

    public function test_現在のチームの管理者なら退会させられる(): void
    {
        [$team, $admin] = $this->makeTeamWithUser(admin: true);
        $victim = Member::factory()->create([
            'team_id' => $team->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($admin, $team)
            ->deleteJson('/api/members/' . $victim->id)
            ->assertStatus(200);

        $this->assertNotNull($victim->fresh()->withdrawal_date);
    }

    public function test_非所属チームのクッキーでもmeは500にならない(): void
    {
        [$teamA, $user] = $this->makeTeamWithUser();
        $foreign = Team::factory()->create();

        $this->actingAsTeamMember($user, $foreign)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('myTeams.0.id', $teamA->id);
    }

    public function test_クッキーが無くても所属チームに解決される(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        Post::factory()->count(2)->create(['team_id' => $team->id]);

        $response = $this->actingAs($user, 'api')
            ->withCredentials()
            ->getJson('/api/posts')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('posts.data'));
    }

    public function test_所属チームが無いユーザーは403になる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->withCredentials()
            ->getJson('/api/me')
            ->assertStatus(403);
    }

    public function test_退会したチームのクッキーは残ったチームに是正される(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $memberA = Member::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
        Member::factory()->create(['user_id' => $user->id, 'team_id' => $teamB->id]);

        // チームAを退会した状態で、古いクッキーのままアクセスする
        $memberA->update(['withdrawal_date' => now()->subDay()->toDateString()]);

        $this->actingAsTeamMember($user, $teamA)
            ->getJson('/api/teams')
            ->assertStatus(200)
            ->assertJsonPath('id', $teamB->id);
    }

    public function test_非所属チームのアンケートCSVはダウンロードできない(): void
    {
        [$teamA, $user] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        $questionnaire = \App\Questionnaire::create([
            'title' => 'Q', 'items' => json_encode([['text' => 'a']]),
            'created_id' => $user->id, 'updated_id' => $user->id,
        ]);
        Post::factory()->create([
            'team_id' => $teamB->id, 'questionnaire_id' => $questionnaire->id,
        ]);

        $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $teamB->id)
            ->get('/questionnaire_download/' . $questionnaire->id)
            ->assertStatus(404);
    }
}
