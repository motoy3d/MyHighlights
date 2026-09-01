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

    public function test_存在しないチームIDのCookieでは404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', '999999')
            ->getJson('/api/teams')
            ->assertStatus(404);
    }

    public function test_未認証では401になる(): void
    {
        $this->getJson('/api/teams')->assertStatus(401);
    }
}
