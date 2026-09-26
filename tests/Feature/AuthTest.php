<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ログイン/ログアウトのテスト。
 *
 * Laravel 6で削除されたAuthenticatesUsersトレイトを自前実装に置き換えたため、
 * 従来と同じ挙動になっていることを確認する。
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログイン画面が表示される(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('csrf-token', false);
    }

    public function test_正しい認証情報でログインできる(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);

        $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ])->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_パスワードが違うとログインできない(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_退会済みユーザはログインできない(): void
    {
        // credentials()でwithdrawal_date is nullを条件に加えている
        User::factory()->withdrawn()->create(['email' => 'gone@example.com']);

        $this->post('/login', [
            'email' => 'gone@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ログイン試行が5回を超えるとロックされる(): void
    {
        User::factory()->create(['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors(['email' => __('auth.failed')]);
        }

        // 6回目は正しいパスワードでもロックされている
        $this->post('/login', [
            'email' => 'throttle@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60])]);

        $this->assertGuest();
    }

    public function test_ログアウトできる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_未認証で保護ページを開くとログイン画面へ飛ばされる(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_パスワード再設定メールの送信画面が表示される(): void
    {
        $this->get('/password/reset')->assertStatus(200);
    }
}
