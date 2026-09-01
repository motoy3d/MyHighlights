<?php

namespace Tests\Feature\Api;

use App\Post;
use App\PostAttachment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
    }

    private function makeAttachment(Post $post): PostAttachment
    {
        return PostAttachment::create([
            'post_id' => $post->id,
            'original_file_name' => 'memo.txt',
            'file_path' => 'storage/post_attachment/memo.txt',
            'file_type' => 'txt',
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ]);
    }

    public function test_添付ファイルを削除できる(): void
    {
        $post = Post::factory()->create(['team_id' => $this->team->id]);
        $attachment = $this->makeAttachment($post);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/post_attachments/' . $attachment->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('post_attachments', ['id' => $attachment->id]);
    }

    public function test_存在しない添付は404(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/post_attachments/999999')
            ->assertStatus(404);
    }

    public function test_他チームの投稿の添付は削除できない(): void
    {
        $otherPost = Post::factory()->create(['team_id' => Team::factory()->create()->id]);
        $attachment = $this->makeAttachment($otherPost);

        $this->actingAsTeamMember($this->user, $this->team)
            ->deleteJson('/api/post_attachments/' . $attachment->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('post_attachments', ['id' => $attachment->id]);
    }
}
