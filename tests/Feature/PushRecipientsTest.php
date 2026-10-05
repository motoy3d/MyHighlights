<?php

namespace Tests\Feature;

use App\Member;
use App\Post;
use App\PostComment;
use App\Schedule;
use App\Support\PushRecipients;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プッシュ通知の宛先の決め方(#110 設計書 §4)。
 */
class PushRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private PushRecipients $recipients;

    private User $actor;          // 操作した本人(保護者)
    private User $coach;          // 指導者
    private User $parent;         // 保護者
    private User $player;         // 選手
    private User $withdrawnMember; // チームを抜けたメンバー
    private User $deletedMember;   // メンバーが論理削除された
    private User $withdrawnUser;   // 退会した利用者
    private User $otherTeamUser;   // 別チームの人

    protected function setUp(): void
    {
        parent::setUp();
        config(['tsubasa.push_enabled_emails' => '*']);
        $this->recipients = new PushRecipients;

        $this->team = Team::factory()->create();
        $this->actor = $this->member($this->team, 'family');
        $this->coach = $this->member($this->team, 'staff');
        $this->parent = $this->member($this->team, 'family');
        $this->player = $this->member($this->team, 'player');

        $this->withdrawnMember = $this->member($this->team, 'family');
        Member::where('user_id', $this->withdrawnMember->id)
            ->update(['withdrawal_date' => now()->subDay()->toDateString()]);

        $this->deletedMember = $this->member($this->team, 'staff');
        Member::where('user_id', $this->deletedMember->id)->first()->delete();

        $this->withdrawnUser = $this->member($this->team, 'staff', User::factory()->withdrawn()->create());

        $this->otherTeamUser = $this->member(Team::factory()->create(), 'staff');
    }

    private function member(Team $team, string $type, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $factory = Member::factory();
        if ($type === 'staff') {
            $factory = $factory->staff();
        } elseif ($type === 'family') {
            $factory = $factory->family();
        }
        $factory->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => $user->name]);

        return $user;
    }

    private function setPrefs(User $user, array $prefs): void
    {
        $user->push_prefs = $prefs;
        $user->save();
    }

    /**
     * @param  iterable<User>  $users
     * @return array<int, int>
     */
    private function ids(iterable $users): array
    {
        $ids = [];
        foreach ($users as $user) {
            $ids[] = (int) $user->id;
        }
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, int>
     */
    private function expected(array $users): array
    {
        return $this->ids($users);
    }

    // 新しい投稿 ------------------------------------------------------------

    public function test_新しい投稿はチームの在籍者全員_本人と退会者と他チームは除く(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);

        $this->assertSame(
            $this->expected([$this->coach, $this->parent, $this->player]),
            $this->ids($this->recipients->forNewPost($post, $this->actor->id))
        );
    }

    public function test_新しい投稿の通知をオフにした人には送らない(): void
    {
        $this->setPrefs($this->parent, ['new_post' => false]);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->actor->id]);

        $this->assertSame(
            $this->expected([$this->coach, $this->player]),
            $this->ids($this->recipients->forNewPost($post, $this->actor->id))
        );
    }

    // 投稿へのコメント --------------------------------------------------------

    public function test_コメントは既定では投稿者とコメントした人だけ(): void
    {
        // 指導者が投稿し、保護者がコメント済み。本人(actor)が新たにコメントした
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->coach->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->parent->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->actor->id]);
        // 退会した人のコメントがあっても宛先にはならない
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->withdrawnMember->id]);

        $this->assertSame(
            $this->expected([$this->coach, $this->parent]),
            $this->ids($this->recipients->forPostComment($post, $this->actor->id))
        );
    }

    public function test_その他の投稿へのコメントをオンにした人にも届く(): void
    {
        $this->setPrefs($this->player, ['comment_on_others' => true]);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->coach->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->actor->id]);

        $this->assertSame(
            $this->expected([$this->coach, $this->player]),
            $this->ids($this->recipients->forPostComment($post, $this->actor->id))
        );
    }

    public function test_自分の投稿へのコメントをオフにした投稿者には届かない(): void
    {
        $this->setPrefs($this->coach, ['comment_on_mine' => false]);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->coach->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->parent->id]);

        $this->assertSame(
            $this->expected([$this->parent]),
            $this->ids($this->recipients->forPostComment($post, $this->actor->id))
        );
    }

    public function test_関わっている人の判定は投稿者とコメントした人(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $this->coach->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->parent->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->parent->id]);

        $ids = $this->recipients->postInvolvedUserIds($post);
        sort($ids);
        $this->assertSame($this->expected([$this->coach, $this->parent]), $ids);
    }

    // 予定の変更・中止 --------------------------------------------------------

    public function test_予定の変更はチームの在籍者全員(): void
    {
        $this->assertSame(
            $this->expected([$this->coach, $this->parent, $this->player]),
            $this->ids($this->recipients->forScheduleChange($this->team->id, $this->actor->id))
        );
    }

    public function test_予定の変更の通知をオフにした人には送らない(): void
    {
        $this->setPrefs($this->coach, ['schedule_change' => false]);

        $this->assertSame(
            $this->expected([$this->parent, $this->player]),
            $this->ids($this->recipients->forScheduleChange($this->team->id, $this->actor->id))
        );
    }

    // 予定へのコメント --------------------------------------------------------

    public function test_予定へのコメントは予定を作った人と指導者だけ(): void
    {
        // 保護者が作った予定に、本人(actor)がコメントした
        $schedule = Schedule::factory()->create([
            'team_id' => $this->team->id, 'created_id' => $this->parent->id,
        ]);

        // player(選手)には届かない。退会・論理削除・別チームの指導者にも届かない
        $this->assertSame(
            $this->expected([$this->coach, $this->parent]),
            $this->ids($this->recipients->forScheduleComment($schedule, $this->actor->id))
        );
    }

    public function test_予定を作った本人がコメントしても本人には届かない(): void
    {
        $schedule = Schedule::factory()->create([
            'team_id' => $this->team->id, 'created_id' => $this->actor->id,
        ]);

        $this->assertSame(
            $this->expected([$this->coach]),
            $this->ids($this->recipients->forScheduleComment($schedule, $this->actor->id))
        );
    }

    public function test_予定へのコメントの通知をオフにした指導者には届かない(): void
    {
        $this->setPrefs($this->coach, ['schedule_comment' => false]);
        $schedule = Schedule::factory()->create([
            'team_id' => $this->team->id, 'created_id' => $this->parent->id,
        ]);

        $this->assertSame(
            $this->expected([$this->parent]),
            $this->ids($this->recipients->forScheduleComment($schedule, $this->actor->id))
        );
    }

    // 段階的な公開 --------------------------------------------------------------

    public function test_公開対象が空なら誰にも送らない(): void
    {
        config(['tsubasa.push_enabled_emails' => '']);
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->assertSame([], $this->ids($this->recipients->forNewPost($post, $this->actor->id)));
        $this->assertSame([], $this->ids($this->recipients->forScheduleChange($this->team->id, $this->actor->id)));
    }

    public function test_公開対象のメールアドレスの人にだけ送る(): void
    {
        config(['tsubasa.push_enabled_emails' =>
            strtoupper($this->coach->email) . ',' . $this->withdrawnUser->email . ',' . $this->otherTeamUser->email]);
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->assertSame(
            $this->expected([$this->coach]),
            $this->ids($this->recipients->forNewPost($post, $this->actor->id))
        );
    }
}
