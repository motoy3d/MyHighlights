<?php

namespace Tests\Feature;

use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LINE Notify連携のテスト。
 *
 * 移行時にconfig/app.phpを差し替えた際、line_notify_*のキーを
 * 落としてしまいclient_idがnullになる不具合があったため、
 * 設定がURLに反映されることを確認する。
 */
class LineNotifyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_LINE認証画面へのリダイレクトに設定値が反映される(): void
    {
        config([
            'tsubasa.line_notify.client_id' => 'dummy-client-id',
            'tsubasa.line_notify.callback_uri' => 'https://example.test/line_auth',
        ]);
        [$team, $user] = $this->makeTeamWithUser();

        $response = $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $team->id)
            ->get('/goto_line_auth')
            ->assertRedirect();

        $location = $response->headers->get('location');
        $this->assertStringContainsString('client_id=dummy-client-id', $location);
        $this->assertStringContainsString('redirect_uri=https://example.test/line_auth', $location);
    }

    public function test_未認証ではログイン画面へ飛ばされる(): void
    {
        // コントローラのコンストラクタで指定しているauthミドルウェアが効いていること
        $this->get('/goto_line_auth')->assertRedirect('/login');
    }
}
