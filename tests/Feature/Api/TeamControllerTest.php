<?php

namespace Tests\Feature\Api;

use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_選択中のチーム情報が返る(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        $this->actingAsTeamMember($user, $team)
            ->getJson('/api/teams')
            ->assertStatus(200)
            ->assertJsonPath('id', $team->id)
            ->assertJsonPath('name', $team->name);
    }

    public function test_どのチームにも所属していないユーザーは403(): void
    {
        // EnsureCurrentTeamIsOwn ミドルウェアが弾く。
        // 存在しないチームIDをクッキーに入れても同じ。
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', '999999')
            ->getJson('/api/teams')
            ->assertStatus(403);
    }

    public function test_未認証では401になる(): void
    {
        $this->getJson('/api/teams')->assertStatus(401);
    }
}
