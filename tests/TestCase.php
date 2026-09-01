<?php

namespace Tests;

use App\Member;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * ログイン済み、かつ指定チームを選択している状態にする。
     *
     * APIの各コントローラはCookieのcurrent_team_idで対象チームを判断するため、
     * 認証だけでなくCookieの設定も必要になる。
     * このCookieはbootstrap/app.phpのencryptCookies(except:)で暗号化対象外にしている。
     */
    protected function actingAsTeamMember(User $user, Team $team): static
    {
        // getJson()などのJSONリクエストはwithCredentials()を付けないと
        // Cookieを送らない(prepareCookiesForJsonRequestが空配列を返す)。
        return $this->actingAs($user, 'api')
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $team->id);
    }

    /**
     * チームと、そこに所属するユーザ(メンバー)をまとめて作る。
     *
     * @return array{0: Team, 1: User, 2: Member}
     */
    protected function makeTeamWithUser(bool $admin = false): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $factory = Member::factory();
        if ($admin) {
            $factory = $factory->admin();
        }
        $member = $factory->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => $user->name,
        ]);

        return [$team, $user, $member];
    }

    protected function json_enc($obj): string
    {
        return json_encode($obj,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
