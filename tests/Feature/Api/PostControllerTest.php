<?php

namespace Tests\Feature\Api;

use App\Category;
use App\Jobs\PostNotificationJob;
use App\Post;
use App\PostAttachment;
use App\PostComment;
use App\PostResponse;
use App\Questionnaire;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser(admin: true);
    }

    // index --------------------------------------------------------

    public function test_自チームの投稿だけが返る(): void
    {
        Post::factory()->count(3)->create(['team_id' => $this->team->id]);
        $otherTeam = Team::factory()->create();
        Post::factory()->count(2)->create(['team_id' => $otherTeam->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('posts.data'));
    }

    public function test_1ページの件数はconfigのtimeline_load_postsに従う(): void
    {
        config(['tsubasa.timeline_load_posts' => 4]);
        Post::factory()->count(9)->create(['team_id' => $this->team->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts')
            ->assertStatus(200);

        $this->assertCount(4, $response->json('posts.data'));
    }

    public function test_キーワードでタイトルと本文を検索できる(): void
    {
        Post::factory()->create(['team_id' => $this->team->id, 'title' => '練習のお知らせ']);
        Post::factory()->create(['team_id' => $this->team->id, 'title' => '別件', 'content' => '練習は中止です']);
        Post::factory()->create(['team_id' => $this->team->id, 'title' => '無関係', 'content' => '無関係']);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts?keyword=練習')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('posts.data'));
    }

    public function test_カテゴリで絞り込める(): void
    {
        $category = Category::factory()->create(['team_id' => $this->team->id]);
        Post::factory()->create(['team_id' => $this->team->id, 'category_id' => $category->id]);
        Post::factory()->count(2)->create(['team_id' => $this->team->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts?category=' . $category->id)
            ->assertStatus(200);

        $this->assertCount(1, $response->json('posts.data'));
    }

    public function test_未読フラグで絞り込める(): void
    {
        $read = Post::factory()->create(['team_id' => $this->team->id]);
        Post::factory()->count(2)->create(['team_id' => $this->team->id]);
        PostResponse::create([
            'user_id' => $this->user->id, 'post_id' => $read->id,
            'read_flg' => true, 'like_flg' => false, 'star_flg' => false,
            'created_id' => $this->user->id, 'updated_id' => $this->user->id,
        ]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts?unread=1')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('posts.data'));
    }

    public function test_未認証では401になる(): void
    {
        $this->getJson('/api/posts')->assertStatus(401);
    }

    // create / searchInit -------------------------------------------

    public function test_新規投稿画面用のカテゴリ一覧が返る(): void
    {
        Category::factory()->count(2)->create(['team_id' => $this->team->id]);
        Category::factory()->create(['team_id' => Team::factory()->create()->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/create')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('categories'));
    }

    public function test_検索画面用のカテゴリ一覧が返る(): void
    {
        Category::factory()->count(3)->create(['team_id' => $this->team->id]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/search_init')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('categories'));
    }

    // store ---------------------------------------------------------

    public function test_投稿を登録できる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/posts', [
                'title' => 'テスト投稿',
                'contents' => '本文です',
                'notification_flg' => 0,
            ])->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'team_id' => $this->team->id,
            'title' => 'テスト投稿',
            'content' => '本文です',
            'created_id' => $this->user->id,
        ]);
    }

    public function test_投稿時に通知ジョブが積まれる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/posts', [
                'title' => '通知あり', 'contents' => '本文', 'notification_flg' => 1,
            ])->assertStatus(200);

        Queue::assertPushed(PostNotificationJob::class);
    }

    public function test_アンケート付きの投稿を登録できる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/posts', [
                'title' => 'アンケート', 'contents' => '参加可否', 'notification_flg' => 0,
                'questionnaire_title' => '参加できますか',
                // 空文字の選択肢は取り除かれる
                'questionnaire_selections' => json_encode([
                    ['text' => '10/1 練習'], ['text' => ''], ['text' => '10/8 試合'],
                ]),
            ])->assertStatus(200);

        $questionnaire = Questionnaire::first();
        $this->assertNotNull($questionnaire);
        $this->assertSame('参加できますか', $questionnaire->title);
        $this->assertCount(2, json_decode($questionnaire->items));
        $this->assertSame($questionnaire->id, Post::first()->questionnaire_id);
    }

    public function test_添付ファイル付きの投稿を登録できる(): void
    {
        Queue::fake();
        Storage::fake('local');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => '添付あり', 'contents' => '本文', 'notification_flg' => 0,
                'files' => [UploadedFile::fake()->create('memo.txt', 8)],
            ])->assertStatus(200);

        $attachment = PostAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertSame('memo.txt', $attachment->original_file_name);
        $this->assertSame('txt', $attachment->file_type);
        // URL用にpublic/ -> storage/ へ置換されている
        $this->assertStringStartsWith('storage/post_attachment/', $attachment->file_path);
    }

    // show ----------------------------------------------------------

    public function test_投稿の詳細を取得できる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);
        PostComment::factory()->count(2)->create([
            'post_id' => $post->id, 'user_id' => $this->user->id,
        ]);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/' . $post->id)
            ->assertStatus(200)
            ->assertJsonPath('post.id', $post->id);

        $this->assertCount(2, $response->json('comments'));
    }

    public function test_初回表示で既読レコードが作られる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);
        $this->assertDatabaseCount('post_responses', 0);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/' . $post->id)
            ->assertStatus(200);

        $this->assertDatabaseHas('post_responses', [
            'user_id' => $this->user->id, 'post_id' => $post->id, 'read_flg' => 1,
        ]);
    }

    public function test_他チームの投稿は取得できない(): void
    {
        $post = Post::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/' . $post->id)
            ->assertStatus(404);
    }

    // update --------------------------------------------------------

    public function test_投稿を更新できる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/posts/' . $post->id, [
                'title' => '更新後', 'contents' => '更新本文', 'notification_flg' => 0,
            ])->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id, 'title' => '更新後', 'content' => '更新本文',
            'updated_id' => $this->user->id,
        ]);
    }

    public function test_他チームの投稿は更新できない(): void
    {
        $post = Post::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->putJson('/api/posts/' . $post->id, ['title' => 'x', 'contents' => 'y'])
            ->assertStatus(404);
    }

    // destroy -------------------------------------------------------

    public function test_投稿を削除すると関連データも消える(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);
        PostComment::factory()->create(['post_id' => $post->id, 'user_id' => $this->user->id]);
        PostResponse::create([
            'user_id' => $this->user->id, 'post_id' => $post->id,
            'read_flg' => true, 'like_flg' => false, 'star_flg' => false,
            'created_id' => $this->user->id, 'updated_id' => $this->user->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/posts/' . $post->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('post_comments', ['post_id' => $post->id]);
        $this->assertDatabaseMissing('post_responses', ['post_id' => $post->id]);
    }

    public function test_他チームの投稿は削除できない(): void
    {
        $post = Post::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/posts/' . $post->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }
}
