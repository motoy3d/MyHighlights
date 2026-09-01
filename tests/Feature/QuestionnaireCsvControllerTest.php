<?php

namespace Tests\Feature;

use App\Post;
use App\Questionnaire;
use App\QuestionnaireAnswer;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * アンケート回答のCSVダウンロードのテスト。
 */
class QuestionnaireCsvControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Questionnaire $questionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        $this->user->forceFill(['name' => 'テスト太郎'])->save();

        $this->questionnaire = Questionnaire::create([
            'title' => '参加できますか',
            'items' => json_encode([['text' => '10/1 練習'], ['text' => '10/8 試合']]),
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ]);
        Post::factory()->create([
            'team_id' => $this->team->id,
            'questionnaire_id' => $this->questionnaire->id,
        ]);
    }

    private function answer(int $questionNo, string $answer): void
    {
        QuestionnaireAnswer::create([
            'questionnaire_id' => $this->questionnaire->id,
            'user_id' => $this->user->id,
            'question_no' => $questionNo,
            'answer' => $answer,
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ]);
    }

    public function test_設問見出しと回答がCSVで返る(): void
    {
        $this->answer(0, '◯');
        $this->answer(1, '✕');

        $body = $this->actingAs($this->user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $this->team->id)
            ->get('/questionnaire_download/' . $this->questionnaire->id)
            ->assertStatus(200)
            ->getContent();

        $this->assertStringContainsString('氏名,10/1 練習,10/8 試合', $body);
        $this->assertStringContainsString('◯', $body);
        $this->assertStringContainsString('✕', $body);
    }

    public function test_未回答の設問は空欄になる(): void
    {
        $this->answer(0, '△');

        $body = $this->actingAs($this->user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $this->team->id)
            ->get('/questionnaire_download/' . $this->questionnaire->id)
            ->assertStatus(200)
            ->getContent();

        // メンバー名 + 1問目の回答 + 2問目は空
        $this->assertMatchesRegularExpression('/,△,\s*$/u', trim($body) . "\n");
    }

    public function test_他チームのアンケートはダウンロードできない(): void
    {
        $otherTeam = Team::factory()->create();

        $this->actingAs($this->user)
            ->withCredentials()
            ->withUnencryptedCookie('current_team_id', (string) $otherTeam->id)
            ->get('/questionnaire_download/' . $this->questionnaire->id)
            ->assertStatus(404);
    }

    public function test_未認証ではログイン画面へ飛ばされる(): void
    {
        $this->get('/questionnaire_download/' . $this->questionnaire->id)
            ->assertRedirect('/login');
    }
}
