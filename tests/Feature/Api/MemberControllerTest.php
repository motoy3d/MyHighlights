<?php

namespace Tests\Feature\Api;

use App\Mail\UserInvitation;
use App\Member;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser(admin: true);
    }

    public function test_自チームのメンバー一覧が返る(): void
    {
        Member::factory()->count(2)->create(['team_id' => $this->team->id]);
        Member::factory()->create(['team_id' => Team::factory()->create()->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/members')
            ->assertStatus(200);

        // setUpで作った管理者メンバー + 2件
        $this->assertCount(3, $response->json());
    }

    public function test_退会済みメンバーは一覧に出ない(): void
    {
        Member::factory()->create([
            'team_id' => $this->team->id,
            'withdrawal_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/members')
            ->assertStatus(200);

        $this->assertCount(1, $response->json());
    }

    public function test_招待なしでメンバーを登録できる(): void
    {
        Mail::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/members', [
                'name' => '新入部員',
                'nameKana' => 'シンニュウブイン',
                'memberTypeSegment' => 0,
                'adminFlg' => false,
                'backno' => 7,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '0',
            ])->assertStatus(200);

        $this->assertDatabaseHas('members', [
            'team_id' => $this->team->id,
            'name' => '新入部員',
            'user_id' => null,
            'type' => 1,
        ]);
        Mail::assertNothingSent();
    }

    public function test_招待ありでユーザが作られ招待メールが送られる(): void
    {
        Mail::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/members', [
                'name' => '招待メンバー',
                'nameKana' => 'ショウタイメンバー',
                'email' => 'invited@example.com',
                'memberTypeSegment' => 0,
                'adminFlg' => false,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
        $this->assertDatabaseHas('members', ['name' => '招待メンバー', 'team_id' => $this->team->id]);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_既存ユーザを招待すると新規作成せずメールだけ送る(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $userCount = User::count();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/members', [
                'name' => '既存ユーザ',
                'email' => 'existing@example.com',
                'memberTypeSegment' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        $this->assertSame($userCount, User::count());
        $this->assertDatabaseHas('members', [
            'name' => '既存ユーザ', 'user_id' => $existing->id,
        ]);
        Mail::assertSent(UserInvitation::class);
    }

    // #79 同じチームに同じユーザーの members が重複して作られないこと -----------------

    public function test_すでにこのチームのメンバーであるユーザは招待できず422になりメールも送られない(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'already@example.com']);
        Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $existing->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/members', [
                'name' => '重複さん',
                'email' => 'already@example.com',
                'memberTypeSegment' => 1,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'このメールアドレスの方はすでにこのチームのメンバーです。');

        // members は増えていない
        $this->assertSame(1, Member::where('team_id', $this->team->id)
            ->where('user_id', $existing->id)->count());
        $this->assertDatabaseMissing('members', ['name' => '重複さん']);
        // 弾かれた登録で招待メールが送られていない
        Mail::assertNothingSent();
    }

    public function test_このチームを退会済みのユーザは再登録できる(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'rejoin@example.com']);
        Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $existing->id,
            'withdrawal_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/members', [
                'name' => '復帰さん',
                'email' => 'rejoin@example.com',
                'memberTypeSegment' => 1,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        $this->assertDatabaseHas('members', [
            'name' => '復帰さん',
            'team_id' => $this->team->id,
            'user_id' => $existing->id,
            'withdrawal_date' => null,
        ]);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_更新で招待したメールアドレスがすでにこのチームのメンバーなら422で何も変わらない(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'already@example.com']);
        Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $existing->id,
        ]);
        // アカウントを持たないメンバー(子どもなど)に、既存メンバーのメールアドレスで招待しようとする
        $child = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => null,
            'name' => '変更前の名前',
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $child->id, [
                'name' => '変更後の名前',
                'email' => 'already@example.com',
                'memberTypeSegment' => 0,
                'adminFlg' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 途中まで保存されていない
        $child->refresh();
        $this->assertNull($child->user_id);
        $this->assertSame('変更前の名前', $child->name);
        Mail::assertNothingSent();
    }

    public function test_更新で自分自身のメールアドレスに再招待するのは重複扱いにならない(): void
    {
        Mail::fake();
        $memberUser = User::factory()->create(['email' => 'self@example.com']);
        $member = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $memberUser->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $member->id, [
                'name' => '再招待さん',
                'email' => 'self@example.com',
                'memberTypeSegment' => 1,
                'adminFlg' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        $this->assertSame($memberUser->id, $member->fresh()->user_id);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_メンバー詳細にメールアドレスが含まれる(): void
    {
        $member = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => User::factory()->create(['email' => 'member@example.com'])->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/members/' . $member->id)
            ->assertStatus(200)
            ->assertJsonPath('email', 'member@example.com');
    }

    public function test_メンバー情報を更新できる(): void
    {
        Mail::fake();
        $memberUser = User::factory()->create(['email' => 'before@example.com']);
        $member = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $memberUser->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $member->id, [
                'name' => '更新後の名前',
                'nameKana' => 'コウシンゴ',
                'email' => 'after@example.com',
                'memberTypeSegment' => 1,
                'adminFlg' => 1,
                'backno' => 99,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '0',
            ])->assertStatus(200);

        $this->assertDatabaseHas('members', [
            'id' => $member->id, 'name' => '更新後の名前', 'backno' => 99, 'admin_flg' => 1,
        ]);
        // 紐づくユーザのメールアドレスも更新される
        $this->assertSame('after@example.com', $memberUser->fresh()->email);
    }

    public function test_他の人が使っているメールアドレスには変更できない(): void
    {
        // users.email は一意。確認せずに保存すると DB のエラーで500になっていた(#124)
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com']);
        $memberUser = User::factory()->create(['email' => 'before@example.com']);
        $member = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $memberUser->id,
            'name' => '変更前の名前',
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $member->id, [
                'name' => '変更後の名前',
                'email' => 'taken@example.com',
                'memberTypeSegment' => 0,
                'adminFlg' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '0',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 名前もメールアドレスも、途中まで保存されていない
        $this->assertSame('変更前の名前', $member->fresh()->name);
        $this->assertSame('before@example.com', $memberUser->fresh()->email);
    }

    public function test_招待で他チームの既存ユーザーに紐づけ直すと元のユーザーのアドレスは変わらない(): void
    {
        // 招待は「そのアドレスの人をこのメンバーに紐づける」操作。
        // 今紐づいている利用者のアドレスを書き換えてはいけない(#124)
        Mail::fake();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $memberUser = User::factory()->create(['email' => 'before@example.com']);
        $member = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => $memberUser->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $member->id, [
                'name' => '紐づけ直し',
                'email' => 'existing@example.com',
                'memberTypeSegment' => 0,
                'adminFlg' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        $this->assertSame($existing->id, $member->fresh()->user_id);
        $this->assertSame('before@example.com', $memberUser->fresh()->email);
        $this->assertSame('existing@example.com', $existing->fresh()->email);
        Mail::assertSent(UserInvitation::class);
    }

    public function test_他チームのメンバーは更新できない(): void
    {
        $member = Member::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/members/' . $member->id, ['name' => 'x'])
            ->assertStatus(404);
    }

    public function test_管理者はメンバーを退会させられる(): void
    {
        $target = Member::factory()->create([
            'team_id' => $this->team->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/members/' . $target->id)
            ->assertStatus(200);

        $this->assertNotNull($target->fresh()->withdrawal_date);
        // 他チームに所属していないユーザ自身も退会扱いになる
        $this->assertNotNull(User::find($target->user_id)->withdrawal_date);
    }

    public function test_管理者でないと退会させられない(): void
    {
        [$team, $normalUser] = $this->makeTeamWithUser(admin: false);
        $target = Member::factory()->create([
            'team_id' => $team->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($normalUser, $team)
            ->deleteJson('/api/members/' . $target->id)
            ->assertStatus(404);

        $this->assertNull($target->fresh()->withdrawal_date);
    }
}
