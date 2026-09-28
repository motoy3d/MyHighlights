<?php

namespace Tests\Feature;

use App\Notifications\CustomResetPassword;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * パスワード再設定のテスト。
 *
 * Laravel 6でSendsPasswordResetEmails/ResetsPasswordsトレイトが
 * 削除されたため自前実装に置き換えており、その挙動を確認する。
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_再設定メールの送信画面が表示される(): void
    {
        $this->get('/password/reset')->assertStatus(200);
    }

    public function test_再設定メールを送信できる(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post('/password/email', ['email' => 'reset@example.com'])
            ->assertSessionHas('status', __('passwords.sent'));

        // Userモデルでカスタム通知に差し替えている
        Notification::assertSentTo($user, CustomResetPassword::class);
    }

    public function test_存在しないメールアドレスではエラーになる(): void
    {
        Notification::fake();

        $this->post('/password/email', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors(['email' => __('passwords.user')]);

        Notification::assertNothingSent();
    }

    public function test_メールアドレスが未入力ならバリデーションエラー(): void
    {
        $this->post('/password/email', [])->assertSessionHasErrors('email');
    }

    public function test_再設定フォームが表示されトークンが埋め込まれる(): void
    {
        $this->get('/password/reset/dummy-token?email=reset@example.com')
            ->assertStatus(200)
            ->assertSee('dummy-token', false)
            ->assertSee('reset@example.com', false);
    }

    public function test_正しいトークンでパスワードを再設定できる(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect('/home');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        // 再設定後はそのままログイン状態になる(旧ResetsPasswordsと同じ挙動)
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_不正なトークンでは再設定できない(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $before = $user->password;

        $this->post('/password/reset', [
            'token' => 'invalid-token',
            'email' => 'reset@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertSame($before, $user->fresh()->password);
        $this->assertGuest();
    }

    public function test_確認用パスワードが一致しないと再設定できない(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_パスワードが6文字未満なら再設定できない(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');
    }
}
