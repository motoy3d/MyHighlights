<?php

namespace Tests\Feature;

use App\Member;
use App\Post;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * チームをまたいだ認可の現状を記録するテスト。
 *
 * 対象チームは current_team_id クッキーで決まるが、このクッキーは
 * jsから読み書きするため暗号化対象外にしてある。つまり利用者が
 * ブラウザの開発者ツールで任意の値に書き換えられる。
 *
 * サーバ側で「そのチームに所属しているか」を検証していないため、
 * 所属していないチームのデータに触れてしまう。移行前からの挙動であり、
 * 移行によって変わったものではない。
 *
 * ここでは「現状こうなっている」ことを固定して、修正した時に
 * このテストが落ちて気付けるようにしている。修正時は本ファイルを
 * 「403/404になること」を確認するテストに書き換えること。
 */
class CrossTeamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_現状_非所属チームのクッキーで投稿一覧が読めてしまう(): void
    {
        [$teamA, $outsider] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        Post::factory()->count(3)->create(['team_id' => $teamB->id]);

        $response = $this->actingAsTeamMember($outsider, $teamB)->getJson('/api/posts');

        $this->assertSame(200, $response->getStatusCode(),
            '所属していないチームの投稿一覧が読めている（要修正）');
        $this->assertCount(3, $response->json('posts.data'));
    }

    public function test_現状_あるチームの管理者が別チームのメンバーを退会させられる(): void
    {
        // 攻撃者はチームAの管理者。チームBには所属していない。
        [$teamA, $attacker] = $this->makeTeamWithUser(admin: true);
        $teamB = Team::factory()->create();
        $victim = Member::factory()->create([
            'team_id' => $teamB->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAsTeamMember($attacker, $teamB)
            ->deleteJson('/api/members/' . $victim->id);

        // MemberController::destroy の管理者判定が team_id で絞られていないため通ってしまう
        $this->assertSame(200, $response->getStatusCode(),
            '別チームの管理者が退会操作をできている（要修正）');
        $this->assertNotNull($victim->fresh()->withdrawal_date);
    }

    public function test_現状_非所属チームのクッキーだとmeが500になる(): void
    {
        [$teamA, $user] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();

        // UserController::getMe が $member->admin_flg を null 参照する
        $this->actingAsTeamMember($user, $teamB)
            ->getJson('/api/me')
            ->assertStatus(500);
    }

    public function test_管理者でない利用者は自チームでも退会操作ができない(): void
    {
        // こちらは正しく防がれている
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
}
