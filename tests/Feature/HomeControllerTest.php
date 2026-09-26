<?php

namespace Tests\Feature;

use App\Member;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン後にホーム画面が表示される(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        $this->actingAs($user)
            ->get('/home')
            ->assertStatus(200)
            ->assertSee('id="app"', false);
    }

    public function test_初回表示でチームCookieが発行される(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        $this->actingAs($user)
            ->get('/home')
            ->assertStatus(200)
            // jsから読むため暗号化しない2つのCookie。
            // assertPlainCookieは復号せずに検証するので、
            // 暗号化除外の設定が効いていることも同時に確認できる。
            ->assertPlainCookie('current_team_id', (string) $team->id)
            ->assertPlainCookie('current_team_name', $team->name);
    }

    public function test_所属チームが無いユーザはログイン画面へ戻される(): void
    {
        // 退会するとmembersのwithdrawal_dateが入り、teams()の結果が空になる
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/home')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_ルートパスもホーム画面を返す(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        $this->actingAs($user)->get('/')->assertStatus(200);
    }
}
