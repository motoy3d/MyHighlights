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

        $this->assertSame(2, $res->json('unseen'));
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

    public function test_一覧を開くと数が0になり以後に届いた分だけ数える(): void
    {
        $this->notify($this->user, 'post-1');
        $this->notify($this->user, 'post-2');
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unseen')->assertJson(['unseen' => 2]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/notices/seen')->assertStatus(200)->assertJson(['unseen' => 0]);
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unseen')->assertJson(['unseen' => 0]);

        $this->travel(1)->seconds();
        $this->notify($this->user, 'post-3');
        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/notices/unseen')->assertJson(['unseen' => 1]);
        // 一覧を開いても、1 件ずつの「開いた」は変わらない
        $opened = array_column(NoticeLog::list($this->user), 'opened');
        $this->assertSame([false, false, false], $opened);
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
        $this->getJson('/api/notices/unseen')->assertStatus(401);
        $this->postJson('/api/notices/seen')->assertStatus(401);
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
}
