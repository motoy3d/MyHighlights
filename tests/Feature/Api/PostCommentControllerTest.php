<?php

namespace Tests\Feature\Api;

use App\Jobs\PostNotificationJob;
use App\Post;
use App\PostComment;
use App\PostCommentAttachment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->post = Post::factory()->create(['team_id' => $this->team->id, 'comment_count' => 0]);
    }

    public function test_コメントを投稿できる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id,
                'comment_text' => 'よろしくお願いします',
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_comments', [
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'comment_text' => 'よろしくお願いします',
        ]);
    }

    public function test_コメント投稿でcomment_countが増える(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id, 'comment_text' => 'コメント',
            ])->assertStatus(200);

        $this->assertSame(1, $this->post->fresh()->comment_count);
    }

    public function test_コメント本文が空でも登録できる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_comments', [
            'post_id' => $this->post->id, 'comment_text' => '',
        ]);
    }

    public function test_通知フラグがtrueのとき通知ジョブが積まれる(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id,
                'comment_text' => '通知するコメント',
                'comment_notification_flg' => 'true',
            ])->assertStatus(200);

        Queue::assertPushed(PostNotificationJob::class);
    }

    public function test_通知フラグがないときは通知ジョブが積まれない(): void
    {
        Queue::fake();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id, 'comment_text' => '通知しない',
            ])->assertStatus(200);

        Queue::assertNothingPushed();
    }

    public function test_添付付きのコメントを投稿できる(): void
    {
        Queue::fake();
        Storage::fake('local');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/post_comments/' . $this->post->id, [
                'post_id' => $this->post->id,
                'comment_text' => '添付あり',
                'comment_files' => [UploadedFile::fake()->create('shiryo.txt', 4)],
            ])->assertStatus(200);

        $attachment = PostCommentAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertSame('shiryo.txt', $attachment->original_file_name);
        $this->assertSame('txt', $attachment->file_type);
    }

    public function test_他チームの投稿にはコメントできない(): void
    {
        $otherPost = Post::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comments/' . $otherPost->id, [
                'post_id' => $otherPost->id, 'comment_text' => 'x',
            ])->assertStatus(404);
    }

    public function test_自分のコメントを削除できる(): void
    {
        $comment = PostComment::factory()->create([
            'post_id' => $this->post->id, 'user_id' => $this->user->id,
        ]);
        $this->post->update(['comment_count' => 1]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson("/api/post_comments/{$this->post->id}/{$comment->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('post_comments', ['id' => $comment->id]);
        $this->assertSame(0, $this->post->fresh()->comment_count);
    }

    public function test_他人のコメントは削除できない(): void
    {
        $comment = PostComment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson("/api/post_comments/{$this->post->id}/{$comment->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('post_comments', ['id' => $comment->id]);
    }
}
