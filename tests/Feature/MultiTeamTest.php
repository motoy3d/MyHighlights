<?php

namespace Tests\Feature;

use App\Category;
use App\Member;
use App\Post;
use App\PostResponse;
use App\Schedule;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 1人のユーザーが複数チームに所属するケースのテスト。
 *
 * 画面のプルダウンでチームを切り替えると、SPAは current_team_id クッキーを
 * 書き換えてから /api/me を取り直す。サーバ側は各APIでこのクッキーを見て
 * 対象チームを決めるため、切り替えで見えるデータが入れ替わることを確認する。
 */
class MultiTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $teamA;
    private Team $teamB;
    private Member $memberA;
    private Member $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'かけもち太郎']);
        $this->teamA = Team::factory()->create(['name' => 'チームA']);
        $this->teamB = Team::factory()->create(['name' => 'チームB']);

        // チームAでは管理者、チームBでは一般メンバー
        $this->memberA = Member::factory()->admin()->create([
            'user_id' => $this->user->id, 'team_id' => $this->teamA->id, 'name' => $this->user->name,
        ]);
        $this->memberB = Member::factory()->create([
            'user_id' => $this->user->id, 'team_id' => $this->teamB->id, 'name' => $this->user->name,
        ]);
    }

    // 所属チーム一覧 ------------------------------------------------

    public function test_所属している全チームがmyTeamsに返る(): void
    {
        $response = $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/me')
            ->assertStatus(200);

        $ids = array_column($response->json('myTeams'), 'id');
        $this->assertCount(2, $ids);
        $this->assertContains($this->teamA->id, $ids);
        $this->assertContains($this->teamB->id, $ids);
    }

    public function test_退会したチームはmyTeamsに含まれない(): void
    {
        $this->memberB->update(['withdrawal_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/me')
            ->assertStatus(200);

        $ids = array_column($response->json('myTeams'), 'id');
        $this->assertSame([$this->teamA->id], $ids);
    }

    public function test_管理者フラグは選択中のチームのものが返る(): void
    {
        $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('currentTeamAdminFlg', 1);

        $this->actingAsTeamMember($this->user, $this->teamB)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('currentTeamAdminFlg', 0);
    }

    // チーム切り替えでデータが入れ替わる ------------------------------

    public function test_チームを切り替えると投稿一覧が入れ替わる(): void
    {
        Post::factory()->count(2)->create(['team_id' => $this->teamA->id]);
        Post::factory()->count(5)->create(['team_id' => $this->teamB->id]);

        $a = $this->actingAsTeamMember($this->user, $this->teamA)->getJson('/api/posts');
        $this->assertCount(2, $a->json('posts.data'));

        $b = $this->actingAsTeamMember($this->user, $this->teamB)->getJson('/api/posts');
        $this->assertCount(5, $b->json('posts.data'));
    }

    public function test_チームを切り替えると予定一覧が入れ替わる(): void
    {
        $month = now()->format('Ym');
        Schedule::factory()->count(3)->create([
            'team_id' => $this->teamA->id,
            'schedule_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        ]);
        Schedule::factory()->create([
            'team_id' => $this->teamB->id,
            'schedule_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        ]);

        $a = $this->actingAsTeamMember($this->user, $this->teamA)->getJson("/api/schedules?month={$month}");
        $this->assertCount(3, $a->json('schedules'));

        $b = $this->actingAsTeamMember($this->user, $this->teamB)->getJson("/api/schedules?month={$month}");
        $this->assertCount(1, $b->json('schedules'));
    }

    public function test_チームを切り替えるとメンバー一覧が入れ替わる(): void
    {
        Member::factory()->count(2)->create(['team_id' => $this->teamA->id]);
        Member::factory()->count(4)->create(['team_id' => $this->teamB->id]);

        $a = $this->actingAsTeamMember($this->user, $this->teamA)->getJson('/api/members');
        $this->assertCount(3, $a->json());  // 本人 + 2

        $b = $this->actingAsTeamMember($this->user, $this->teamB)->getJson('/api/members');
        $this->assertCount(5, $b->json());  // 本人 + 4
    }

    public function test_チームを切り替えるとカテゴリ一覧が入れ替わる(): void
    {
        Category::factory()->count(2)->create(['team_id' => $this->teamA->id]);
        Category::factory()->count(3)->create(['team_id' => $this->teamB->id]);

        $a = $this->actingAsTeamMember($this->user, $this->teamA)->getJson('/api/posts/create');
        $this->assertCount(2, $a->json('categories'));

        $b = $this->actingAsTeamMember($this->user, $this->teamB)->getJson('/api/posts/create');
        $this->assertCount(3, $b->json('categories'));
    }

    public function test_チームを切り替えるとteams取得も入れ替わる(): void
    {
        $this->actingAsTeamMember($this->user, $this->teamA)->getJson('/api/teams')
            ->assertJsonPath('name', 'チームA');

        $this->actingAsTeamMember($this->user, $this->teamB)->getJson('/api/teams')
            ->assertJsonPath('name', 'チームB');
    }

    // 書き込みも選択中のチームに紐づく --------------------------------

    public function test_投稿は選択中のチームに作成される(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->teamB)
            ->postJson('/api/posts', ['title' => 'B宛て', 'contents' => '本文', 'notification_flg' => 0])
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', ['title' => 'B宛て', 'team_id' => $this->teamB->id]);
        $this->assertDatabaseMissing('posts', ['title' => 'B宛て', 'team_id' => $this->teamA->id]);
    }

    public function test_予定は選択中のチームに作成される(): void
    {
        $this->actingAsTeamMember($this->user, $this->teamB)
            ->postJson('/api/schedules', [
                'schedule_date' => '2026-12-01', 'title' => 'B宛て予定',
                'allday_flg' => 'true', 'notification_flg' => 'false',
            ])->assertStatus(200);

        $this->assertDatabaseHas('schedules', ['title' => 'B宛て予定', 'team_id' => $this->teamB->id]);
    }

    public function test_一方のチームの投稿はもう一方からは見えない(): void
    {
        $postA = Post::factory()->create(['team_id' => $this->teamA->id]);

        $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/posts/' . $postA->id)->assertStatus(200);

        $this->actingAsTeamMember($this->user, $this->teamB)
            ->getJson('/api/posts/' . $postA->id)->assertStatus(404);
    }

    public function test_未読件数はチームごとに独立している(): void
    {
        // 未読は「自分が加入した後に作成された投稿」だけが対象
        $this->joinedLongAgo();
        Post::factory()->count(2)->create(['team_id' => $this->teamA->id]);
        Post::factory()->count(4)->create(['team_id' => $this->teamB->id]);

        $a = $this->actingAsTeamMember($this->user, $this->teamA)->getJson('/api/posts');
        $b = $this->actingAsTeamMember($this->user, $this->teamB)->getJson('/api/posts');

        $this->assertSame(2, $a->json('unreadCount'));
        $this->assertSame(4, $b->json('unreadCount'));
    }

    public function test_既読にしても他チームの未読件数に影響しない(): void
    {
        $this->joinedLongAgo();
        $postA = Post::factory()->create(['team_id' => $this->teamA->id]);
        Post::factory()->count(3)->create(['team_id' => $this->teamB->id]);

        PostResponse::create([
            'user_id' => $this->user->id, 'post_id' => $postA->id,
            'read_flg' => true, 'like_flg' => false, 'star_flg' => false,
            'created_id' => $this->user->id, 'updated_id' => $this->user->id,
        ]);

        $this->assertSame(0, $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/posts')->json('unreadCount'));
        $this->assertSame(3, $this->actingAsTeamMember($this->user, $this->teamB)
            ->getJson('/api/posts')->json('unreadCount'));
    }

    public function test_加入前に作成された投稿は未読件数に含まれない(): void
    {
        // 加入日より前の投稿は「読む前提が無い」ため未読に数えない
        $this->memberA->forceFill(['created_at' => now()->subYear()])->save();

        Post::factory()->create([
            'team_id' => $this->teamA->id,
            'created_at' => now()->subYears(2),   // 加入前
        ]);
        Post::factory()->count(2)->create([
            'team_id' => $this->teamA->id,
            'created_at' => now()->subMonth(),    // 加入後
        ]);

        $this->assertSame(2, $this->actingAsTeamMember($this->user, $this->teamA)
            ->getJson('/api/posts')->json('unreadCount'));
    }

    /**
     * 未読判定は members.created_at を基準にするため、
     * 投稿より前に加入していた状態を作る。
     */
    private function joinedLongAgo(): void
    {
        $this->memberA->forceFill(['created_at' => now()->subYear()])->save();
        $this->memberB->forceFill(['created_at' => now()->subYear()])->save();
    }

    // ホーム画面のクッキー是正 ----------------------------------------

    public function test_ホーム表示時に所属チームのクッキーはそのまま維持される(): void
    {
        $this->actingAs($this->user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $this->teamB->id)
            ->get('/home')
            ->assertStatus(200)
            // 所属しているチームなので上書きされない
            ->assertCookieMissing('current_team_id');
    }

    public function test_ホーム表示時に所属していないチームのクッキーは是正される(): void
    {
        $foreign = Team::factory()->create();

        $this->actingAs($this->user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $foreign->id)
            ->get('/home')
            ->assertStatus(200)
            // 1件目の所属チームに書き換えられる
            ->assertPlainCookie('current_team_id', (string) $this->teamA->id)
            ->assertPlainCookie('current_team_name', 'チームA');
    }

    public function test_クッキーが無い場合は1件目の所属チームがセットされる(): void
    {
        $this->actingAs($this->user)
            ->get('/home')
            ->assertStatus(200)
            ->assertPlainCookie('current_team_id', (string) $this->teamA->id);
    }

    // 退会 ------------------------------------------------------------

    public function test_片方のチームを退会してももう片方は使える(): void
    {
        $this->actingAs($this->user)
            ->post('/withdrawal', ['user_id' => $this->user->id, 'team_id' => $this->teamA->id]);

        // チームAのメンバーだけ退会日が入る
        $this->assertNotNull($this->memberA->fresh()->withdrawal_date);
        $this->assertNull($this->memberB->fresh()->withdrawal_date);
        // 他チームに残っているのでユーザー自体は退会にならない
        $this->assertNull($this->user->fresh()->withdrawal_date);

        // 残ったチームBは引き続き利用できる
        $this->actingAsTeamMember($this->user->fresh(), $this->teamB)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('myTeams.0.id', $this->teamB->id);
    }

    public function test_全チームを退会するとユーザーも退会扱いになる(): void
    {
        foreach ([$this->teamA, $this->teamB] as $team) {
            $this->actingAs($this->user)
                ->post('/withdrawal', ['user_id' => $this->user->id, 'team_id' => $team->id]);
        }

        $this->assertNotNull($this->user->fresh()->withdrawal_date);
    }

    public function test_所属チームが無くなるとホームからログイン画面へ戻される(): void
    {
        $this->memberA->update(['withdrawal_date' => now()->toDateString()]);
        $this->memberB->update(['withdrawal_date' => now()->toDateString()]);

        $this->actingAs($this->user)
            ->get('/home')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_退会済みユーザーはログインできない(): void
    {
        // withdrawal_date は User の $fillable に無いため update() では反映されない
        $this->user->forceFill(['withdrawal_date' => now()->toDateString()])->save();

        $this->post('/login', ['email' => $this->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
