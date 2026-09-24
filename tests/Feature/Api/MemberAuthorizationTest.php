<?php

namespace Tests\Feature\Api;

use App\Mail\UserInvitation;
use App\Member;
use App\Post;
use App\PostComment;
use App\PostResponse;
use App\Questionnaire;
use App\QuestionnaireAnswer;
use App\Schedule;
use App\ScheduleComment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * メンバー編集の権限と、別のアカウントへの紐づけ直し(#124)。
 *
 * 名前などは管理者でなくても編集できるが、乗っ取り・権限の奪取につながる操作
 * (アカウントのメールアドレス変更・招待/紐づけ直し・管理者の設定)は管理者だけに限る。
 */
class MemberAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        [$this->team, $this->admin] = $this->makeTeamWithUser(admin: true);
    }

    /** このチームの一般メンバー(管理者でない)を作る */
    private function makeMember(string $email, int $type = 2): array
    {
        $user = User::factory()->create(['email' => $email]);
        $member = Member::factory()->create([
            'team_id' => $this->team->id, 'user_id' => $user->id, 'type' => $type, 'admin_flg' => 0,
        ]);
        return [$user, $member];
    }

    private function payload(Member $member, array $overrides = []): array
    {
        return array_merge([
            'name' => $member->name,
            'email' => User::find($member->user_id)?->email,
            'memberTypeSegment' => $member->type - 1,
            'adminFlg' => (bool) $member->admin_flg,
            'selectedAvatarFilename' => 'noimage.png',
            'invitationFlg' => '0',
        ], $overrides);
    }

    // 管理者でない人 ------------------------------------------------------

    public function test_管理者でない人は他のメンバーのメールアドレスを変えられない(): void
    {
        // 変えられると、そのアドレスでパスワードを再設定して乗っ取れる
        [$attacker] = $this->makeMember('attacker@example.com');
        [$coach, $coachMember] = $this->makeMember('coach@example.com');

        $this->actingAsTeamMember($attacker, $this->team)
            ->putJson('/api/members/' . $coachMember->id, $this->payload($coachMember, [
                'name' => '名前は変えられる',
                'email' => 'evil@example.com',
            ]))->assertStatus(200);

        $this->assertSame('coach@example.com', $coach->fresh()->email);
        // 名前などの編集は、これまでどおり管理者でなくてもできる
        $this->assertSame('名前は変えられる', $coachMember->fresh()->name);
    }

    public function test_管理者でない人は紐づけ直しができない(): void
    {
        [$attacker] = $this->makeMember('attacker@example.com');
        [$coach, $coachMember] = $this->makeMember('coach@example.com');

        $this->actingAsTeamMember($attacker, $this->team)
            ->putJson('/api/members/' . $coachMember->id, $this->payload($coachMember, [
                'email' => 'attacker@example.com',
                'invitationFlg' => '1',
            ]))->assertStatus(403);

        $this->assertSame($coach->id, $coachMember->fresh()->user_id);
        Mail::assertNothingSent();
    }

    public function test_管理者でない人は自分や他人を管理者にできない(): void
    {
        [$attacker, $attackerMember] = $this->makeMember('attacker@example.com');

        $this->actingAsTeamMember($attacker, $this->team)
            ->putJson('/api/members/' . $attackerMember->id, $this->payload($attackerMember, [
                'adminFlg' => true,
            ]))->assertStatus(200);
        $this->assertSame(0, (int) $attackerMember->fresh()->admin_flg);

        // メンバー追加でも管理者は付けられない
        $this->actingAsTeamMember($attacker, $this->team)
            ->postJson('/api/members', [
                'name' => '追加した人', 'memberTypeSegment' => 1, 'adminFlg' => true,
                'selectedAvatarFilename' => 'noimage.png', 'invitationFlg' => '0',
            ])->assertStatus(200);
        $this->assertSame(0, (int) Member::where('name', '追加した人')->value('admin_flg'));
    }

    public function test_管理者でない人が管理者を編集しても管理者は外れない(): void
    {
        // 画面は管理者の設定を送ってくる(値は読み込んだまま)。管理者でない人の保存では無視する
        [$attacker] = $this->makeMember('attacker@example.com');
        $adminMember = Member::where('user_id', $this->admin->id)->first();

        $this->actingAsTeamMember($attacker, $this->team)
            ->putJson('/api/members/' . $adminMember->id, $this->payload($adminMember, [
                'adminFlg' => false,
            ]))->assertStatus(200);
        $this->assertSame(1, (int) $adminMember->fresh()->admin_flg);
    }

    public function test_管理者は管理者の設定を変えられる(): void
    {
        [, $member] = $this->makeMember('member@example.com');

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, ['adminFlg' => true]))
            ->assertStatus(200);
        $this->assertSame(1, (int) $member->fresh()->admin_flg);
    }

    // メールアドレスの形 ------------------------------------------------------

    public function test_管理者でもメールアドレスを空にはできない(): void
    {
        // 空で保存するとその人はログインできなくなる
        [$user, $member] = $this->makeMember('keep@example.com');

        foreach (['', '   ', 'no-at-mark', 'a b@example.com'] as $bad) {
            $this->actingAsTeamMember($this->admin, $this->team)
                ->putJson('/api/members/' . $member->id, $this->payload($member, [
                    'name' => '変えない', 'email' => $bad,
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }
        $this->assertSame('keep@example.com', $user->fresh()->email);
        $this->assertNotSame('変えない', $member->fresh()->name);
    }

    public function test_携帯キャリアの古い形のアドレスには変えられる(): void
    {
        // RFC の厳密な検証では弾かれる形(. が続く、@ の前が . で終わる)
        [$user, $member] = $this->makeMember('before@example.com');

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, [
                'email' => 'abc..def.@docomo.ne.jp',
            ]))->assertStatus(200);
        $this->assertSame('abc..def.@docomo.ne.jp', $user->fresh()->email);
    }

    // 紐づけ直し ----------------------------------------------------------

    public function test_紐づけ直すとこのチームでの書き込みが移り他のチームの分は残る(): void
    {
        [$old, $member] = $this->makeMember('old@example.com');
        $new = User::factory()->create(['email' => 'new@example.com']);
        $otherTeam = Team::factory()->create();
        // 元のアカウントは他のチームにも所属している(退会扱いにはならない)
        Member::factory()->create(['team_id' => $otherTeam->id, 'user_id' => $old->id]);

        $post = Post::factory()->create(['team_id' => $this->team->id, 'created_id' => $old->id, 'updated_id' => $old->id]);
        $otherPost = Post::factory()->create(['team_id' => $otherTeam->id, 'created_id' => $old->id]);
        $comment = PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $old->id, 'created_id' => $old->id]);
        $otherComment = PostComment::factory()->create(['post_id' => $otherPost->id, 'user_id' => $old->id]);
        $schedule = Schedule::factory()->create(['team_id' => $this->team->id, 'created_id' => $old->id]);
        $scheduleComment = ScheduleComment::create([
            'schedule_id' => $schedule->id, 'user_id' => $old->id, 'comment_text' => '参加します',
            'created_id' => $old->id, 'updated_id' => $old->id,
        ]);

        // 既読：移す先に行が無い投稿は移る。両方にある投稿は移す先を正とし、元の行は残す
        $both = Post::factory()->create(['team_id' => $this->team->id]);
        foreach ([[$old, $post], [$old, $both], [$new, $both], [$old, $otherPost]] as [$u, $p]) {
            PostResponse::create([
                'user_id' => $u->id, 'post_id' => $p->id, 'read_flg' => true, 'like_flg' => $u->is($old),
                'star_flg' => false, 'created_id' => $u->id, 'updated_id' => $u->id,
            ]);
        }

        // アンケート：移す先が未回答のものだけ移る
        $q1 = Questionnaire::create(['title' => 'A', 'items' => '[]', 'created_id' => $old->id, 'updated_id' => $old->id]);
        $q2 = Questionnaire::create(['title' => 'B', 'items' => '[]', 'created_id' => $old->id, 'updated_id' => $old->id]);
        Post::factory()->create(['team_id' => $this->team->id, 'questionnaire_id' => $q1->id]);
        Post::factory()->create(['team_id' => $this->team->id, 'questionnaire_id' => $q2->id]);
        foreach ([[$old, $q1], [$old, $q2], [$new, $q2]] as [$u, $q]) {
            QuestionnaireAnswer::create([
                'questionnaire_id' => $q->id, 'user_id' => $u->id, 'question_no' => 0, 'answer' => '◯',
                'created_id' => $u->id, 'updated_id' => $u->id,
            ]);
        }

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, [
                'email' => 'new@example.com', 'invitationFlg' => '1',
            ]))->assertStatus(200);

        $this->assertSame($new->id, $member->fresh()->user_id);
        // このチームの書き込みは移る
        $this->assertSame($new->id, $post->fresh()->created_id);
        $this->assertSame($new->id, $post->fresh()->updated_id);
        $this->assertSame($new->id, $comment->fresh()->user_id);
        $this->assertSame($new->id, $schedule->fresh()->created_id);
        $this->assertSame($new->id, $scheduleComment->fresh()->user_id);
        $this->assertTrue(PostResponse::where('user_id', $new->id)->where('post_id', $post->id)->exists());
        $this->assertSame(1, QuestionnaireAnswer::where('user_id', $new->id)->where('questionnaire_id', $q1->id)->count());
        // 両方にあったものは移さない(二重にしない)。元の行は消さずに残す
        $this->assertSame(1, PostResponse::where('user_id', $new->id)->where('post_id', $both->id)->count());
        $this->assertTrue(PostResponse::where('user_id', $old->id)->where('post_id', $both->id)->exists());
        $this->assertSame(1, QuestionnaireAnswer::where('user_id', $new->id)->where('questionnaire_id', $q2->id)->count());
        $this->assertSame(1, QuestionnaireAnswer::where('user_id', $old->id)->where('questionnaire_id', $q2->id)->count());
        // 他のチームの書き込みは元のアカウントのまま
        $this->assertSame($old->id, $otherPost->fresh()->created_id);
        $this->assertSame($old->id, $otherComment->fresh()->user_id);
        $this->assertTrue(PostResponse::where('user_id', $old->id)->where('post_id', $otherPost->id)->exists());
        // 他のチームに所属しているので退会扱いにはならない
        $this->assertNull($old->fresh()->withdrawal_date);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_紐づけ直した元のアカウントがどのチームにも所属しなくなれば退会扱いになる(): void
    {
        [$old, $member] = $this->makeMember('old@example.com');
        User::factory()->create(['email' => 'new@example.com']);

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, [
                'email' => 'new@example.com', 'invitationFlg' => '1',
            ]))->assertStatus(200);

        $this->assertNotNull($old->fresh()->withdrawal_date);
    }

    public function test_退会済みのアカウントに紐づけ直すと退会が取り消される(): void
    {
        [, $member] = $this->makeMember('old@example.com');
        $withdrawn = User::factory()->create(['email' => 'back@example.com', 'withdrawal_date' => now()->subDay()]);

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, [
                'email' => 'back@example.com', 'invitationFlg' => '1',
            ]))->assertStatus(200);

        $this->assertSame($withdrawn->id, $member->fresh()->user_id);
        $this->assertNull($withdrawn->fresh()->withdrawal_date);
    }

    public function test_紐づけ直しの途中で失敗したら何も変わらず招待も送らない(): void
    {
        // 移す先がすでにこのチームのメンバー(#79)。メンバーの名前も保存されない
        [$old, $member] = $this->makeMember('old@example.com');
        [$already] = $this->makeMember('already@example.com');

        $this->actingAsTeamMember($this->admin, $this->team)
            ->putJson('/api/members/' . $member->id, $this->payload($member, [
                'name' => '変えない', 'email' => 'already@example.com', 'invitationFlg' => '1',
            ]))->assertStatus(422);

        $this->assertSame($old->id, $member->fresh()->user_id);
        $this->assertNotSame('変えない', $member->fresh()->name);
        Mail::assertNothingSent();
    }
}
