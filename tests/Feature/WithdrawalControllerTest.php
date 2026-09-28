<?php

namespace Tests\Feature;

use App\Member;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_自分でチームを退会できる(): void
    {
        [$team, $user, $member] = $this->makeTeamWithUser();

        $this->actingAs($user)
            ->post('/withdrawal', ['user_id' => $user->id, 'team_id' => $team->id]);

        $this->assertNotNull($member->fresh()->withdrawal_date);
        // 他チームに所属していなければユーザ自身も退会になる
        $this->assertNotNull($user->fresh()->withdrawal_date);
    }

    public function test_他チームに残っている場合ユーザは退会にならない(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $otherTeam = Team::factory()->create();
        Member::factory()->create(['user_id' => $user->id, 'team_id' => $otherTeam->id]);

        $this->actingAs($user)
            ->post('/withdrawal', ['user_id' => $user->id, 'team_id' => $team->id]);

        $this->assertNull($user->fresh()->withdrawal_date);
    }

    public function test_未認証ではログイン画面へ飛ばされる(): void
    {
        $this->post('/withdrawal', [])->assertRedirect('/login');
    }
}
