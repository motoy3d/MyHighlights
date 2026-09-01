<?php

namespace Tests\Feature\Api;

use App\Post;
use App\PostComment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * コメントへのいいねのテスト。
 */
class PostCommentResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Post $post;
    private PostComment $comment;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->post = Post::factory()->create(['team_id' => $this->team->id]);
        $this->comment = PostComment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'like_count' => 0,
        ]);
    }

    public function test_コメントにいいねできる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comment_responses/' . $this->comment->id, [
                'post_comment_id' => $this->comment->id, 'like_flg' => 1,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_comment_responses', [
            'user_id' => $this->user->id,
            'post_comment_id' => $this->comment->id,
            'like_flg' => 1,
        ]);
        $this->assertSame(1, $this->comment->fresh()->like_count);
    }

    public function test_いいねを解除するとlike_countが減る(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comment_responses/' . $this->comment->id, [
                'post_comment_id' => $this->comment->id, 'like_flg' => 1,
            ])->assertStatus(200);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comment_responses/' . $this->comment->id, [
                'post_comment_id' => $this->comment->id, 'like_flg' => 0,
            ])->assertStatus(200);

        $this->assertSame(0, $this->comment->fresh()->like_count);
    }

    public function test_他チームの投稿のコメントにはいいねできない(): void
    {
        $otherPost = Post::factory()->create(['team_id' => Team::factory()->create()->id]);
        $otherComment = PostComment::factory()->create([
            'post_id' => $otherPost->id, 'user_id' => $this->user->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_comment_responses/' . $otherComment->id, [
                'post_comment_id' => $otherComment->id, 'like_flg' => 1,
            ])->assertStatus(404);
    }
}
