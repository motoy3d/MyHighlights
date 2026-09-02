<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 移行前から変えてはいけない設定値を固定する。
 *
 * Laravel 13の標準config/*.phpをそのまま採用すると既定値が変わり、
 * 本番で実害が出るものがある。ここで値を固定して再発を防ぐ。
 */
class ConfigInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_ローカルディスクのrootはstorage_appのまま(): void
    {
        // Laravel 11で既定が storage/app/private に変わったが、添付ファイルは
        // storePublicly('public/...') で保存し public/storage 経由で配信するため、
        // storage/app でないとアップロードもURLも壊れる。
        $this->assertSame(storage_path('app'), config('filesystems.disks.local.root'));
    }

    public function test_publicディスクのrootはstorage_app_public(): void
    {
        $this->assertSame(storage_path('app/public'), config('filesystems.disks.public.root'));
    }

    public function test_セッションCookie名がASCIIで移行前と同じ(): void
    {
        config(['app.name' => 'Tsubasa⬆︎UP']);
        // config()の再評価のためにセッション設定を組み直す
        $cookie = \Illuminate\Support\Str::slug('Tsubasa⬆︎UP', '_') . '_session';

        $this->assertSame('tsubasaup_session', $cookie);
        // 実際の設定値もASCIIのみであること
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_\-]+\z/', config('session.cookie'));
    }

    public function test_SameSiteは未指定(): void
    {
        // 'lax' にすると LINE Notify の form_post コールバックで
        // セッションCookieが送られず連携が失敗する
        $this->assertNull(config('session.same_site'));
    }

    public function test_API応答でセッションクッキーにSameSiteが付かない(): void
    {
        // Sanctum標準の EnsureFrontendRequestsAreStateful は
        // session.same_site を 'lax' に強制する。そうなるとAPI応答で
        // セッションクッキーが Lax として再発行され、LINE Notifyの
        // コールバック(別サイトからのPOST)でセッションが送られなくなる。
        // App\Http\Middleware\EnsureFrontendRequestsAreStateful で
        // 上書きを外しているので、それが効いていることを確認する。
        [$team, $user] = $this->makeTeamWithUser();

        $response = $this->actingAsTeamMember($user, $team)
            ->getJson('/api/me')
            ->assertStatus(200);

        $this->assertNull(config('session.same_site'),
            'stateful APIリクエスト後に session.same_site が書き換えられている');

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertNull($cookie->getSameSite(),
                "クッキー {$cookie->getName()} に SameSite が付いている");
        }
    }

    public function test_パスワードリセットのテーブル名は既存のまま(): void
    {
        // Laravel 11の既定は password_reset_tokens だが、
        // 本番のテーブル名は password_resets
        $this->assertSame('password_resets', config('auth.passwords.users.table'));
    }

    public function test_ユーザーモデルの位置(): void
    {
        $this->assertSame(\App\User::class, config('auth.providers.users.model'));
    }

    public function test_APIガードはsanctum(): void
    {
        $this->assertSame('sanctum', config('auth.guards.api.driver'));
    }

    public function test_タイムゾーンとロケール(): void
    {
        $this->assertSame('Asia/Tokyo', config('app.timezone'));
        $this->assertSame('ja', config('app.locale'));
    }

    public function test_LINE_Notifyの設定キーが存在する(): void
    {
        // config/app.php 差し替え時に落として一度壊した箇所
        $this->assertTrue(config()->has('tsubasa.line_notify.client_id'));
        $this->assertTrue(config()->has('tsubasa.line_notify.client_secret'));
        $this->assertTrue(config()->has('tsubasa.line_notify.callback_uri'));
    }

    public function test_アプリ固有の設定キーが存在する(): void
    {
        $this->assertIsInt(config('tsubasa.schedule_data_loading_months'));
        $this->assertIsInt(config('tsubasa.timeline_load_posts'));
    }

    public function test_失敗ジョブのuuid列が存在する(): void
    {
        // 既定の database-uuids ドライバは uuid 列へ書き込む
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('failed_jobs', 'uuid'),
            'failed_jobsにuuid列が無いと、ジョブ失敗時の記録に失敗する'
        );
    }

    public function test_旧env名のフォールバックが効く(): void
    {
        // 本番の .env は MAIL_DRIVER / QUEUE_DRIVER などの旧キー名のままなので、
        // 新キーが未設定でも読めること
        $this->assertNotNull(config('mail.default'));
        $this->assertNotNull(config('queue.default'));
        $this->assertNotNull(config('cache.default'));
        $this->assertNotNull(config('filesystems.default'));
    }
}
