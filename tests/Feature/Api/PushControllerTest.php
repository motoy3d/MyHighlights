<?php

namespace Tests\Feature\Api;

use App\Notifications\PushNotice;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * Webプッシュの購読・設定API(#110 設計書 §7.3)。
 */
class PushControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        config(['tsubasa.push_enabled_emails' => '']);
        config(['webpush.vapid.public_key' => 'BTestPublicKey']);
    }

    private function subscriptionPayload(string $endpoint = self::ENDPOINT): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BPublicKeyOfBrowser', 'auth' => 'AuthSecret'],
            'content_encoding' => 'aes128gcm',
        ];
    }

    // GET /api/push/config ------------------------------------------------

    public function test_公開対象が空なら無効(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertStatus(200)
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('vapid_public_key', 'BTestPublicKey');
    }

    public function test_公開対象がアスタリスクなら全員有効(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('enabled', true);
    }

    public function test_メールアドレスが一覧にあれば有効_大文字小文字と空白は無視(): void
    {
        config(['tsubasa.push_enabled_emails' =>
            'someone@example.com, ' . strtoupper($this->user->email) . ' ,other@example.com']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('enabled', true);
    }

    public function test_メールアドレスが一覧に無ければ無効(): void
    {
        config(['tsubasa.push_enabled_emails' => 'someone@example.com']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('enabled', false);
    }

    public function test_設定の既定値が返る(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('preferences', [
                'new_post' => true,
                'comment_on_mine' => true,
                'comment_on_others' => false,
                'schedule_change' => true,
                'schedule_comment' => true,
            ]);
    }

    public function test_VAPID公開鍵が未設定ならnull(): void
    {
        config(['webpush.vapid.public_key' => null]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('vapid_public_key', null);
    }

    public function test_未ログインは401(): void
    {
        $this->getJson('/api/push/config')->assertStatus(401);
    }

    // PUT /api/push/preferences ----------------------------------------------

    public function test_設定を保存できる_送ったキーだけ変わる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/push/preferences', [
                'preferences' => ['new_post' => false, 'comment_on_others' => true],
            ])
            ->assertStatus(200)
            ->assertJsonPath('preferences', [
                'new_post' => false,
                'comment_on_mine' => true,
                'comment_on_others' => true,
                'schedule_change' => true,
                'schedule_comment' => true,
            ]);

        $user = $this->user->fresh();
        $this->assertFalse($user->wantsPush('new_post'));
        $this->assertTrue($user->wantsPush('comment_on_others'));
        $this->assertTrue($user->wantsPush('schedule_change'));

        // 保存後に取得しても同じ
        $this->actingAsTeamMember($user, $this->team)
            ->getJson('/api/push/config')
            ->assertJsonPath('preferences.new_post', false)
            ->assertJsonPath('preferences.comment_on_others', true);
    }

    public function test_未知のキーは422(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/push/preferences', [
                'preferences' => ['new_post' => false, 'unknown_key' => true],
            ])
            ->assertStatus(422);

        $this->assertNull($this->user->fresh()->push_prefs);
    }

    public function test_真偽値でない値は422(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/push/preferences', [
                'preferences' => ['new_post' => 'yes'],
            ])
            ->assertStatus(422);
    }

    public function test_preferencesが無いと422(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/push/preferences', [])
            ->assertStatus(422);
    }

    // POST /api/push/subscriptions -------------------------------------------

    public function test_購読を登録できる(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/subscriptions', $this->subscriptionPayload())
            ->assertStatus(204);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $this->user->id,
            'endpoint' => self::ENDPOINT,
            'public_key' => 'BPublicKeyOfBrowser',
            'auth_token' => 'AuthSecret',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_content_encodingが無ければaes128gcm(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);
        $payload = $this->subscriptionPayload();
        unset($payload['content_encoding']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/subscriptions', $payload)
            ->assertStatus(204);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => self::ENDPOINT, 'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_同じ端末で登録し直すと1行のまま更新される(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);
        $client = $this->actingAsTeamMember($this->user, $this->team);

        $client->postJson('/api/push/subscriptions', $this->subscriptionPayload())->assertStatus(204);
        $payload = $this->subscriptionPayload();
        $payload['keys']['auth'] = 'NewAuthSecret';
        $client->postJson('/api/push/subscriptions', $payload)->assertStatus(204);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['auth_token' => 'NewAuthSecret']);
    }

    public function test_公開対象外の利用者は購読できない(): void
    {
        config(['tsubasa.push_enabled_emails' => 'someone@example.com']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/subscriptions', $this->subscriptionPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_httpsでないendpointは422(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/subscriptions', $this->subscriptionPayload('http://example.com/push/1'))
            ->assertStatus(422);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_keysが無いと422(): void
    {
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertStatus(422);
    }

    // DELETE /api/push/subscriptions -----------------------------------------

    public function test_購読を解除できる(): void
    {
        $this->user->updatePushSubscription(self::ENDPOINT, 'key', 'token', 'aes128gcm');

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/push/subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertStatus(204);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => self::ENDPOINT]);
    }

    public function test_他人の購読は解除できない(): void
    {
        $other = User::factory()->create();
        $other->updatePushSubscription(self::ENDPOINT, 'key', 'token', 'aes128gcm');

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/push/subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertStatus(204);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => self::ENDPOINT, 'subscribable_id' => $other->id,
        ]);
    }

    // POST /api/push/test ----------------------------------------------------

    public function test_テスト通知を自分の全端末に送る(): void
    {
        Notification::fake();
        config(['tsubasa.push_enabled_emails' => '*']);
        $this->user->updatePushSubscription(self::ENDPOINT, 'key', 'token', 'aes128gcm');
        $this->user->updatePushSubscription(self::ENDPOINT . '-pc', 'key2', 'token2', 'aes128gcm');

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/test')
            ->assertStatus(200)
            ->assertJson(['sent' => 2]);

        Notification::assertSentTo($this->user, PushNotice::class,
            function (PushNotice $notice, array $channels) {
                $payload = $notice->toWebPush($this->user)->toArray()['notification'];

                return $channels === [WebPushChannel::class]
                    && $payload['title'] === 'Tsubasa⬆︎UP'
                    && $payload['body'] === 'テスト通知です。この端末で通知を受け取れます。'
                    && $payload['tag'] === 'test'
                    && $payload['data']['url'] === '/home?launcher=true'
                    && $payload['data']['nid'] === $notice->nid;
            });
    }

    public function test_購読が無ければテスト通知は0件(): void
    {
        Notification::fake();
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/test')
            ->assertStatus(200)
            ->assertJson(['sent' => 0]);

        Notification::assertNothingSent();
    }

    public function test_公開対象外の利用者にはテスト通知を送らない(): void
    {
        Notification::fake();
        $this->user->updatePushSubscription(self::ENDPOINT, 'key', 'token', 'aes128gcm');

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/push/test')
            ->assertStatus(403);

        Notification::assertNothingSent();
    }
}
