<?php

namespace Tests\Feature\Api;

use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser(admin: true);
    }

    public function test_ログインユーザの情報を取得できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('id', $this->user->id)
            ->assertJsonPath('email', $this->user->email)
            ->assertJsonPath('currentTeamAdminFlg', 1)
            ->assertJsonPath('myTeams.0.id', $this->team->id);
    }

    public function test_名前を更新できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updateName', ['name' => '変更後の名前'])
            ->assertStatus(200);

        $this->assertSame('変更後の名前', $this->user->fresh()->name);
    }

    public function test_カナを更新できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updateNameKana', ['name_kana' => 'ヘンコウゴ'])
            ->assertStatus(200);

        $this->assertSame('ヘンコウゴ', $this->user->fresh()->name_kana);
    }

    public function test_メールアドレスを更新できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updateEmail', ['email' => 'changed@example.com'])
            ->assertStatus(200);

        $this->assertSame('changed@example.com', $this->user->fresh()->email);
    }

    public function test_パスワードを更新できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updatePassword', [
                'current_password' => 'password',
                'new_password' => 'new-password-123',
            ])->assertStatus(200);

        $this->assertTrue(Hash::check('new-password-123', $this->user->fresh()->password));
    }

    public function test_メール通知フラグを更新できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updateMailNotificationFlg', ['mail_notification_flg' => 0])
            ->assertStatus(200);

        $this->assertSame(0, (int) $this->user->fresh()->mail_notification_flg);
    }

    public function test_メール通知フラグに不正値を渡すと400(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/users/updateMailNotificationFlg', ['mail_notification_flg' => 'yes'])
            ->assertStatus(400);
    }


}
