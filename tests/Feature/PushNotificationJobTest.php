<?php

namespace Tests\Feature;

use App\Jobs\PushNotificationJob;
use App\Member;
use App\Notifications\PushNotice;
use App\Post;
use App\PostComment;
use App\Schedule;
use App\ScheduleComment;
use App\Support\PushRecipients;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * PushNotificationJob の実行(誰に何が送られるか)。
 * 実際の送信は Notification::fake() で差し替える。
 */
class PushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $actor;   // 操作した本人
    private User $coach;   // 指導者(購読あり)
    private User $parent;  // 保護者(購読あり)
    private User $noDevice; // 保護者(購読なし)

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['tsubasa.push_enabled_emails' => '*']);

        $this->team = Team::factory()->create(['name' => '横浜SCつばさ']);
        $this->actor = $this->member('family', '山田');
        $this->coach = $this->member('staff', '佐藤コーチ');
        $this->parent = $this->member('family', '鈴木');
        $this->noDevice = $this->member('family', '田中');

        foreach ([$this->actor, $this->coach, $this->parent] as $user) {
            $user->updatePushSubscription(
                'https://fcm.googleapis.com/fcm/send/device-' . $user->id, 'key', 'token', 'aes128gcm');
        }
    }

    private function member(string $type, string $name): User
    {
        $user = User::factory()->create(['name' => $name . '(ユーザー名)']);
        $factory = $type === 'staff' ? Member::factory()->staff() : Member::factory()->family();
        $factory->create(['user_id' => $user->id, 'team_id' => $this->team->id, 'name' => $name]);

        return $user;
    }

    private function runJob(PushNotificationJob $job): void
    {
        $job->handle(new PushRecipients);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(User $user): array
    {
        $payload = null;
        Notification::assertSentTo($user, PushNotice::class,
            function (PushNotice $notice, array $channels) use ($user, &$payload) {
                $payload = $notice->toWebPush($user)->toArray()['notification'];

                return $channels === [WebPushChannel::class];
            });

        return $payload;
    }

    // 新しい投稿 ------------------------------------------------------------

    public function test_新しい投稿を本人以外の購読者に送る(): void
    {
        $post = Post::factory()->create([
            'team_id' => $this->team->id, 'title' => '9/27 練習試合のお知らせ', 'created_id' => $this->actor->id,
        ]);

        $this->runJob(PushNotificationJob::newPost($post, $this->actor->id));

        Notification::assertNotSentTo($this->actor, PushNotice::class);
        Notification::assertNotSentTo($this->noDevice, PushNotice::class); // 購読なし
        Notification::assertSentTo($this->parent, PushNotice::class);
        $payload = $this->payloadFor($this->coach);
        $this->assertSame('横浜SCつばさ', $payload['title']);
        // 名前はそのチームでのメンバー名
        $this->assertSame('山田さんが投稿しました：9/27 練習試合のお知らせ', $payload['body']);
        $this->assertSame('post-' . $post->id, $payload['tag']);
        $this->assertSame(
            ['url' => "/home?launcher=true&team={$this->team->id}&post={$post->id}"],
            $payload['data']
        );
        $this->assertSame(rtrim(config('app.url'), '/') . '/appicon.png', $payload['icon']);
        $this->assertTrue($payload['renotify']);
    }

    public function test_通知の種類をオフにした人には送らない(): void
    {
        $this->parent->push_prefs = ['new_post' => false];
        $this->parent->save();
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);

        $this->runJob(PushNotificationJob::newPost($post, $this->actor->id));

        Notification::assertNotSentTo($this->parent, PushNotice::class);
        Notification::assertSentTo($this->coach, PushNotice::class);
    }

    public function test_公開対象外の人には購読があっても送らない(): void
    {
        config(['tsubasa.push_enabled_emails' => $this->coach->email]);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);

        $this->runJob(PushNotificationJob::newPost($post, $this->actor->id));

        Notification::assertSentTo($this->coach, PushNotice::class);
        Notification::assertNotSentTo($this->parent, PushNotice::class);
    }

    public function test_公開対象が空なら何も送らない(): void
    {
        config(['tsubasa.push_enabled_emails' => '']);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);

        $this->runJob(PushNotificationJob::newPost($post, $this->actor->id));

        Notification::assertNothingSent();
    }

    public function test_投稿が削除済みなら何も送らない(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);
        $job = PushNotificationJob::newPost($post, $this->actor->id);
        $post->delete();

        $this->runJob($job);

        Notification::assertNothingSent();
    }

    // 投稿へのコメント --------------------------------------------------------

    public function test_コメントは投稿者とコメントした人に送る(): void
    {
        $post = Post::factory()->create([
            'team_id' => $this->team->id, 'title' => '遠征のお知らせ', 'created_id' => $this->coach->id,
        ]);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id, 'user_id' => $this->actor->id, 'comment_text' => "承知しました。\nよろしくお願いします",
        ]);

        $this->runJob(PushNotificationJob::postComment($post, $comment, $this->actor->id));

        // 保護者(鈴木)は関わっていないので、既定(comment_on_others=false)では届かない
        Notification::assertNotSentTo($this->parent, PushNotice::class);
        Notification::assertNotSentTo($this->actor, PushNotice::class);
        $payload = $this->payloadFor($this->coach);
        $this->assertSame('山田さんが「遠征のお知らせ」にコメントしました：承知しました。 よろしくお願いします', $payload['body']);
        $this->assertSame('post-' . $post->id, $payload['tag']);
        $this->assertSame("/home?launcher=true&team={$this->team->id}&post={$post->id}", $payload['data']['url']);
    }

    // 予定の変更・削除 --------------------------------------------------------

    public function test_予定の変更をチームの全員に送る(): void
    {
        $schedule = Schedule::factory()->create([
            'team_id' => $this->team->id, 'schedule_date' => '2026-09-27', 'title' => '練習試合',
        ]);

        $this->runJob(PushNotificationJob::scheduleChanged($schedule, $this->actor->id));

        Notification::assertSentTo($this->parent, PushNotice::class);
        Notification::assertNotSentTo($this->actor, PushNotice::class);
        $payload = $this->payloadFor($this->coach);
        $this->assertSame('予定が変更されました：9/27 練習試合', $payload['body']);
        $this->assertSame('schedule-' . $schedule->id, $payload['tag']);
        $this->assertSame(
            "/home?launcher=true&team={$this->team->id}&schedule={$schedule->id}&date=2026-09-27",
            $payload['data']['url']
        );
    }

    public function test_予定の削除は削除前の内容で送る(): void
    {
        $job = PushNotificationJob::scheduleDeleted([
            'id' => 999, 'team_id' => $this->team->id, 'title' => '練習', 'schedule_date' => '2026-10-04',
        ], $this->actor->id);

        $this->runJob($job);

        $payload = $this->payloadFor($this->parent);
        $this->assertSame('予定が削除されました：10/4 練習', $payload['body']);
        $this->assertSame('schedule-999', $payload['tag']);
        // 予定はもう無いので、その日のカレンダーを開く
        $this->assertSame("/home?launcher=true&team={$this->team->id}&date=2026-10-04", $payload['data']['url']);
    }

    // 予定へのコメント --------------------------------------------------------

    public function test_予定へのコメントは予定を作った人と指導者に送る(): void
    {
        $creator = $this->member('family', '高橋');
        $creator->updatePushSubscription('https://fcm.googleapis.com/fcm/send/creator', 'key', 'token', 'aes128gcm');
        $schedule = Schedule::factory()->create([
            'team_id' => $this->team->id, 'schedule_date' => '2026-09-27', 'title' => '練習試合',
            'created_id' => $creator->id,
        ]);
        $comment = ScheduleComment::create([
            'schedule_id' => $schedule->id, 'user_id' => $this->actor->id, 'comment_text' => '参加します',
            'created_id' => $this->actor->id, 'updated_id' => $this->actor->id,
        ]);

        $this->runJob(PushNotificationJob::scheduleComment($schedule, $comment, $this->actor->id));

        Notification::assertSentTo($creator, PushNotice::class);
        Notification::assertNotSentTo($this->parent, PushNotice::class); // 指導者でも作成者でもない
        Notification::assertNotSentTo($this->actor, PushNotice::class);
        $payload = $this->payloadFor($this->coach);
        $this->assertSame('山田さんが予定「9/27 練習試合」にコメントしました：参加します', $payload['body']);
        $this->assertSame('schedule-' . $schedule->id, $payload['tag']);
        $this->assertSame(
            "/home?launcher=true&team={$this->team->id}&schedule={$schedule->id}&date=2026-09-27",
            $payload['data']['url']
        );
    }

    // 通知の中身 ------------------------------------------------------------

    public function test_本文は全角60文字程度で切る(): void
    {
        $notice = new PushNotice('チーム', str_repeat('あ', 100), 'post-1', '/home');
        $body = $notice->toWebPush($this->coach)->toArray()['notification']['body'];

        // '…' の幅をいくつと数えるかはPHPの版で違うので、幅の上限と末尾だけを確かめる
        $this->assertLessThanOrEqual(PushNotice::BODY_WIDTH, mb_strwidth($body, 'UTF-8'));
        $this->assertGreaterThanOrEqual(58, mb_strlen($body, 'UTF-8'));
        $this->assertLessThan(100, mb_strlen($body, 'UTF-8'));
        $this->assertStringEndsWith('…', $body);
    }

    public function test_短い本文はそのまま(): void
    {
        $notice = new PushNotice('チーム', '短い本文', 'post-1', '/home');

        $this->assertSame('短い本文', $notice->toWebPush($this->coach)->toArray()['notification']['body']);
    }

    public function test_Declarative_Web_Pushの形式でnavigateは完全なアドレス(): void
    {
        config(['app.url' => 'https://tsubasa.example.test']);
        $notice = new PushNotice('チーム', '本文', 'post-1', '/home?launcher=true&team=1&post=2');
        $payload = $notice->toWebPush($this->coach)->toArray();

        $this->assertSame(8030, $payload['web_push']);
        $this->assertSame('チーム', $payload['notification']['title']);
        $this->assertSame('https://tsubasa.example.test/home?launcher=true&team=1&post=2', $payload['notification']['navigate']);
        // iOS は icon を基準なしで読むので、相対だとこの形式として認識されない
        $this->assertSame('https://tsubasa.example.test/appicon.png', $payload['notification']['icon']);
        // 通知は sw.js が表示する（前面に戻ったときにタップされた通知を割り出すため）
        $this->assertTrue($payload['mutable']);
        // Declarative Web Push に対応していないブラウザ(sw.js が表示する)向けに相対のアドレスも残す
        $this->assertSame(['url' => '/home?launcher=true&team=1&post=2'], $payload['notification']['data']);
    }

    public function test_TTLとurgencyを指定している(): void
    {
        $notice = new PushNotice('チーム', '本文', 'post-1', '/home');

        $this->assertSame(['TTL' => 86400, 'urgency' => 'high'], $notice->toWebPush($this->coach)->getOptions());
    }
}
