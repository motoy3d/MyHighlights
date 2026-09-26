<?php

namespace Tests\Feature\Api;

use App\Post;
use App\PostResponse;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 投稿への既読/いいね/スターのテスト。
 */
class PostResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->post = Post::factory()->create(['team_id' => $this->team->id]);
    }

    private function existingResponse(array $overrides = []): PostResponse
    {
        return PostResponse::create(array_merge([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'read_flg' => true,
            'like_flg' => false,
            'star_flg' => false,
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ], $overrides));
    }

    public function test_初回は既読フラグで新規作成される(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_responses/' . $this->post->id, [
                'post_id' => $this->post->id, 'read_flg' => 1,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_responses', [
            'user_id' => $this->user->id, 'post_id' => $this->post->id, 'read_flg' => 1,
        ]);
    }

    public function test_既存レコードがある場合いいねを更新できる(): void
    {
        $this->existingResponse();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_responses/' . $this->post->id, [
                'post_id' => $this->post->id, 'like_flg' => 1,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_responses', [
            'user_id' => $this->user->id, 'post_id' => $this->post->id, 'like_flg' => 1,
        ]);
    }

    public function test_いいねを解除できる(): void
    {
        $this->existingResponse(['like_flg' => true]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_responses/' . $this->post->id, [
                'post_id' => $this->post->id, 'like_flg' => 0,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_responses', [
            'user_id' => $this->user->id, 'post_id' => $this->post->id, 'like_flg' => 0,
        ]);
    }

    public function test_スターを更新できる(): void
    {
        $this->existingResponse();

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_responses/' . $this->post->id, [
                'post_id' => $this->post->id, 'star_flg' => 1,
            ])->assertStatus(200);

        $this->assertDatabaseHas('post_responses', [
            'user_id' => $this->user->id, 'post_id' => $this->post->id, 'star_flg' => 1,
        ]);
    }

    public function test_他チームの投稿には反応できない(): void
    {
        $otherPost = Post::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/post_responses/' . $otherPost->id, [
                'post_id' => $otherPost->id, 'read_flg' => 1,
            ])->assertStatus(404);
    }
}
