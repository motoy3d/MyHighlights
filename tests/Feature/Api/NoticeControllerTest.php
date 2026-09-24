<?php

namespace Tests\Feature\Api;

use App\Notifications\PushNotice;
use App\Post;
use App\Support\NoticeLog;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * アプリ内のお知らせ一覧(🔔)の API(#125)。
 */
class NoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
    }

    private function notify(User $user, string $tag = 'post-1', string $body = '本文', string $type = 'new_post'): PushNotice
    {
        $notice = new PushNotice($this->team->name, $body, $tag, '/home?launcher=true&team=' . $this->team->id . '&post=' . substr($tag, 5));
        NoticeLog::record($user, $notice, $type, $this->team->id);

        return $notice;
    }

    public function test_一覧は本人の分だけを新しい順に返す(): void
    {
        $first = $this->notify($this->user, 'post-1', '古い方');
        $this->travel(1)->seconds();
        $second = $this->notify($this->user, 'post-2', '新しい方', 'post_comment');
        $this->notify(User::factory()->create(), 'post-3', '他の人の分');

        $res = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices')
            ->assertStatus(200);

        $this->assertSame(2, $res->json('unopened'));
        $this->assertSame([$second->nid, $first->nid], array_column($res->json('items'), 'nid'));
        $this->assertSame('新しい方', $res->json('items.0.body'));
        $this->assertSame('post_comment', $res->json('items.0.type'));
        $this->assertSame($this->team->id, $res->json('items.0.team_id'));
        $this->assertSame($this->team->name, $res->json('items.0.title'));
        $this->assertFalse($res->json('items.0.opened'));
        // 一覧から開くリンクには nid を付けない(一覧で開いたにしてから開く)
        $this->assertStringNotContainsString('nid=', $res->json('items.0.url'));
    }

    public function test_30日より前の通知は一覧に出ない(): void
    {
        $this->travel(-31)->days();
        $this->notify($this->user, 'post-1', '古すぎる');
        $this->travelBack();
        $this->notify($this->user, 'post-2', '最近');

        $res = $this->actingAsTeamMember($this->user, $this->team)->getJson('/api/notices');
        $this->assertSame(['最近'], array_column($res->json('items'), 'body'));
    }

    public function test_数はまだ開いていない通知の数で一覧を開いただけでは減らない(): void
    {
        $a = $this->notify($this->user, 'post-1');
        $this->notify($this->user, 'post-2');
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unopened')->assertJson(['unopened' => 2]);

        // 一覧を開いても減らない(2026-09-24 実機：開いただけで 0 になり、まだ見ていない通知が分からなくなった)
        $this->actingAsTeamMember($this->user, $this->team)->getJson('/api/notices')->assertJson(['unopened' => 2]);
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unopened')->assertJson(['unopened' => 2]);

        // 1 件開くと 1 減る
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', ['nid' => $a->nid])->assertJson(['unopened' => 1]);

        // 新しく届くと増える
        $this->notify($this->user, 'post-3');
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unopened')->assertJson(['unopened' => 2]);
    }

    public function test_30日より前のまだ開いていない通知は数えない(): void
    {
        $this->travel(-31)->days();
        $this->notify($this->user, 'post-1');
        $this->travelBack();
        $this->notify($this->user, 'post-2');

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unopened')->assertJson(['unopened' => 1]);
    }

    public function test_nidで1件を開いたにする(): void
    {
        $a = $this->notify($this->user, 'post-1');
        $b = $this->notify($this->user, 'post-2');

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', ['nid' => $a->nid])->assertStatus(200);

        $opened = collect(NoticeLog::list($this->user))->pluck('opened', 'nid');
        $this->assertTrue($opened[$a->nid]);
        $this->assertFalse($opened[$b->nid]);
    }

    public function test_すべて開いたにする(): void
    {
        $this->notify($this->user, 'post-1');
        $this->notify($this->user, 'post-2');

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', ['all' => true])->assertStatus(200);

        $this->assertSame([true, true], array_column(NoticeLog::list($this->user), 'opened'));
    }

    public function test_他の人の通知は開いたにできない(): void
    {
        $other = User::factory()->create();
        $notice = $this->notify($other, 'post-1');

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', ['nid' => $notice->nid])->assertStatus(200);
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', ['all' => true])->assertStatus(200);

        $this->assertFalse(NoticeLog::list($other)[0]['opened']);
    }

    public function test_nidもallも無ければ422(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/open', [])->assertStatus(422);
    }

    public function test_ログインしていないと使えない(): void
    {
        $this->getJson('/api/notices')->assertStatus(401);
        $this->getJson('/api/notices/unopened')->assertStatus(401);
        $this->postJson('/api/notices/open', ['all' => true])->assertStatus(401);
    }

    public function test_投稿の詳細を開くとその投稿についての通知が開いたになる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);
        $this->notify($this->user, 'post-' . $post->id, '新しい投稿');
        $this->notify($this->user, 'post-' . $post->id, 'コメント', 'post_comment');
        $this->notify($this->user, 'post-999999', '別の投稿');

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/' . $post->id)->assertStatus(200);

        $opened = collect(NoticeLog::list($this->user))->pluck('opened', 'body');
        $this->assertTrue($opened['新しい投稿']);
        $this->assertTrue($opened['コメント']);
        $this->assertFalse($opened['別の投稿']);
    }

    public function test_投稿を削除するとその投稿についてのお知らせが全員の一覧から消える(): void
    {
        // 残すと、一覧からタップしても投稿が無く開けない(2026-09-25 実機)
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->user->id]);
        $other = User::factory()->create();
        $this->notify($this->user, 'post-' . $post->id, '消える');
        $this->notify($other, 'post-' . $post->id, '他の人のも消える');
        $this->notify($this->user, 'post-999999', '残る');

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/posts/' . $post->id)->assertStatus(200);

        $this->assertSame(['残る'], array_column(NoticeLog::list($this->user), 'body'));
        $this->assertSame([], NoticeLog::list($other));
        $this->assertSame(1, NoticeLog::unopenedCount($this->user));
    }

    public function test_予定を削除するとその予定のこれまでのお知らせが消え削除の通知だけが残る(): void
    {
        // 残すと、タップしてもカレンダーに予定が無い(2026-09-25 実機)
        config(['tsubasa.push_enabled_emails' => '*']);
        $schedule = \App\Schedule::factory()->create([
            'team_id' => $this->team->id, 'schedule_date' => now()->addDays(3)->toDateString(),
        ]);
        $this->notify($this->user, 'schedule-' . $schedule->id, '予定が変更されました', 'schedule_change');
        $this->notify($this->user, 'post-1', '関係ない投稿');
        $other = User::factory()->create();
        \App\Member::factory()->create(['team_id' => $this->team->id, 'user_id' => $other->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/schedules/' . $schedule->id)->assertStatus(200);

        // 自分の分：変更の通知は消え、関係ない投稿の通知は残る
        $this->assertSame(['関係ない投稿'], array_column(NoticeLog::list($this->user), 'body'));
        // 他のメンバーには「予定が削除されました」が届いている(削除の後に記録されるので消えない)
        $items = NoticeLog::list($other);
        $this->assertCount(1, $items);
        $this->assertSame('schedule_deleted', $items[0]['type']);
    }
}
