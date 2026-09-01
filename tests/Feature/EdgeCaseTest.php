<?php

namespace Tests\Feature;

use App\Mail\UserInvitation;
use App\Member;
use App\Post;
use App\PostComment;
use App\Questionnaire;
use App\QuestionnaireAnswer;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 運用で起こりうるケースのテスト。
 */
class EdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    // 招待による複数チーム所属 --------------------------------------

    public function test_既存ユーザーを別チームに招待すると2チーム所属になる(): void
    {
        Mail::fake();
        [$teamA, $existing] = $this->makeTeamWithUser();
        [$teamB, $admin] = $this->makeTeamWithUser(admin: true);

        $this->actingAsTeamMember($admin, $teamB)
            ->postJson('/api/members', [
                'name' => '兼任さん',
                'email' => $existing->email,
                'memberTypeSegment' => 0,
                'selectedAvatarFilename' => 'noimage.png',
                'invitationFlg' => '1',
            ])->assertStatus(200);

        Mail::assertSent(UserInvitation::class);

        $ids = array_column(
            $this->actingAsTeamMember($existing, $teamA)->getJson('/api/me')->json('myTeams'),
            'id'
        );
        $this->assertCount(2, $ids);
        $this->assertContains($teamB->id, $ids);
    }

    // 退会メンバーの扱い ---------------------------------------------

    public function test_退会したメンバーの投稿は残るが投稿者名は表示されない(): void
    {
        [$team, $viewer] = $this->makeTeamWithUser();
        $author = User::factory()->create(['name' => '退会太郎']);
        $authorMember = Member::factory()->create([
            'team_id' => $team->id, 'user_id' => $author->id, 'name' => '退会太郎',
        ]);
        $post = Post::factory()->create(['team_id' => $team->id, 'created_id' => $author->id]);

        $before = $this->actingAsTeamMember($viewer, $team)->getJson('/api/posts');
        $this->assertSame('退会太郎', $before->json('posts.data.0.created_name'));

        $authorMember->update(['withdrawal_date' => now()->toDateString()]);

        $after = $this->actingAsTeamMember($viewer, $team)->getJson('/api/posts');
        // 投稿自体は残る
        $this->assertCount(1, $after->json('posts.data'));
        // 投稿者名はメンバー表から引けなくなる
        $this->assertNull($after->json('posts.data.0.created_name'));
    }

    public function test_退会したメンバーのアンケート回答は集計から除かれる(): void
    {
        [$team, $viewer] = $this->makeTeamWithUser();
        $questionnaire = Questionnaire::create([
            'title' => '参加可否', 'items' => json_encode([['text' => '10/1']]),
            'created_id' => $viewer->id, 'updated_id' => $viewer->id,
        ]);
        $post = Post::factory()->create([
            'team_id' => $team->id, 'questionnaire_id' => $questionnaire->id,
        ]);

        $leaver = User::factory()->create();
        $leaverMember = Member::factory()->create(['team_id' => $team->id, 'user_id' => $leaver->id]);
        foreach ([$viewer, $leaver] as $u) {
            QuestionnaireAnswer::create([
                'questionnaire_id' => $questionnaire->id, 'user_id' => $u->id,
                'question_no' => 0, 'answer' => '◯',
                'created_id' => $u->id, 'updated_id' => $u->id,
            ]);
        }

        $before = $this->actingAsTeamMember($viewer, $team)->getJson('/api/posts/' . $post->id);
        $this->assertSame(2, $before->json('questionnaire.items.0.answerCounts.◯'));

        $leaverMember->update(['withdrawal_date' => now()->toDateString()]);

        $after = $this->actingAsTeamMember($viewer, $team)->getJson('/api/posts/' . $post->id);
        $this->assertSame(1, $after->json('questionnaire.items.0.answerCounts.◯'));
    }

    // ページング ------------------------------------------------------

    public function test_投稿一覧の2ページ目が取得できる(): void
    {
        config(['tsubasa.timeline_load_posts' => 3]);
        [$team, $user] = $this->makeTeamWithUser();
        Post::factory()->count(7)->create(['team_id' => $team->id]);

        $p1 = $this->actingAsTeamMember($user, $team)->getJson('/api/posts');
        $this->assertCount(3, $p1->json('posts.data'));

        $p2 = $this->actingAsTeamMember($user, $team)->getJson('/api/posts?page=2');
        $this->assertCount(3, $p2->json('posts.data'));

        $ids1 = array_column($p1->json('posts.data'), 'id');
        $ids2 = array_column($p2->json('posts.data'), 'id');
        $this->assertEmpty(array_intersect($ids1, $ids2), '1ページ目と2ページ目が重複している');
    }

    // アンケート付き投稿の削除 ----------------------------------------

    public function test_アンケート付き投稿を削除すると回答も消える(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $questionnaire = Questionnaire::create([
            'title' => 'Q', 'items' => json_encode([['text' => 'a']]),
            'created_id' => $user->id, 'updated_id' => $user->id,
        ]);
        $post = Post::factory()->create([
            'team_id' => $team->id, 'questionnaire_id' => $questionnaire->id,
        ]);
        QuestionnaireAnswer::create([
            'questionnaire_id' => $questionnaire->id, 'user_id' => $user->id,
            'question_no' => 0, 'answer' => '◯',
            'created_id' => $user->id, 'updated_id' => $user->id,
        ]);

        $this->actingAsTeamMember($user, $team)
            ->deleteJson('/api/posts/' . $post->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('questionnaires', ['id' => $questionnaire->id]);
        $this->assertDatabaseMissing('questionnaire_answers', ['questionnaire_id' => $questionnaire->id]);
    }

    public function test_アンケートなしの投稿も削除できる(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = Post::factory()->create(['team_id' => $team->id, 'questionnaire_id' => null]);

        $this->actingAsTeamMember($user, $team)
            ->deleteJson('/api/posts/' . $post->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    // 同一ユーザーの重複操作 ------------------------------------------

    public function test_同じ投稿に2回いいねしても行は増えない(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = Post::factory()->create(['team_id' => $team->id]);

        // 初回は既読レコードを作る
        $this->actingAsTeamMember($user, $team)
            ->postJson('/api/post_responses/' . $post->id, ['post_id' => $post->id, 'read_flg' => 1])
            ->assertStatus(200);

        foreach ([1, 1] as $flg) {
            $this->actingAsTeamMember($user, $team)
                ->postJson('/api/post_responses/' . $post->id, ['post_id' => $post->id, 'like_flg' => $flg])
                ->assertStatus(200);
        }

        $this->assertSame(1, \App\PostResponse::where('user_id', $user->id)
            ->where('post_id', $post->id)->count());
    }

    public function test_同じコメントに2回いいねしても行は増えない(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = Post::factory()->create(['team_id' => $team->id]);
        $comment = PostComment::factory()->create([
            'post_id' => $post->id, 'user_id' => $user->id, 'like_count' => 0,
        ]);

        foreach ([1, 1] as $flg) {
            $this->actingAsTeamMember($user, $team)
                ->postJson('/api/post_comment_responses/' . $comment->id, [
                    'post_comment_id' => $comment->id, 'like_flg' => $flg,
                ])->assertStatus(200);
        }

        $this->assertSame(1, \App\PostCommentResponse::where('user_id', $user->id)
            ->where('post_comment_id', $comment->id)->count());
    }

    // 添付の削除 ------------------------------------------------------

    public function test_コメントを削除するとcomment_countが減る(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = Post::factory()->create(['team_id' => $team->id, 'comment_count' => 0]);

        $ids = [];
        foreach (['一件目', '二件目'] as $text) {
            $this->actingAsTeamMember($user, $team)
                ->postJson('/api/post_comments/' . $post->id, [
                    'post_id' => $post->id, 'comment_text' => $text,
                ])->assertStatus(200);
        }
        $this->assertSame(2, $post->fresh()->comment_count);

        $comment = PostComment::where('post_id', $post->id)->first();
        $this->actingAsTeamMember($user, $team)
            ->deleteJson("/api/post_comments/{$post->id}/{$comment->id}")
            ->assertStatus(200);

        $this->assertSame(1, $post->fresh()->comment_count);
    }

    // 検索 ------------------------------------------------------------

    public function test_キーワード検索は他チームの投稿を拾わない(): void
    {
        [$teamA, $user] = $this->makeTeamWithUser();
        $teamB = Team::factory()->create();
        Post::factory()->create(['team_id' => $teamA->id, 'title' => '合宿のお知らせ']);
        Post::factory()->create(['team_id' => $teamB->id, 'title' => '合宿の連絡']);

        $r = $this->actingAsTeamMember($user, $teamA)->getJson('/api/posts?keyword=合宿');
        $this->assertCount(1, $r->json('posts.data'));
    }

    public function test_検索キーワードに部分一致しない投稿は返らない(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        Post::factory()->create(['team_id' => $team->id, 'title' => 'AAA', 'content' => 'BBB']);

        $r = $this->actingAsTeamMember($user, $team)->getJson('/api/posts?keyword=ZZZ');
        $this->assertCount(0, $r->json('posts.data'));
    }
}
