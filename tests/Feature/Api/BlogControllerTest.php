<?php

namespace Tests\Feature\Api;

use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ブログ(RSS)取得のテスト。
 *
 * RSSの取得は外部通信になるため、ここではURL未設定時の挙動のみを確認する。
 */
class BlogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_RSSのURLが未設定なら空配列が返る(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $this->assertNull($team->blog_rss);

        $this->actingAsTeamMember($user, $team)
            ->getJson('/api/blog')
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    public function test_未認証では401になる(): void
    {
        $this->getJson('/api/blog')->assertStatus(401);
    }
}
