<?php

namespace Tests\Feature\Api;

use App\Post;
use App\Questionnaire;
use App\QuestionnaireAnswer;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * アンケート回答のテスト。
 */
class QuestionnaireControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;
    private Post $post;
    private Questionnaire $questionnaire;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();

        $this->questionnaire = Questionnaire::create([
            'title' => '参加できますか',
            'items' => json_encode([['text' => '10/1 練習'], ['text' => '10/8 試合']]),
            'created_id' => $this->user->id,
            'updated_id' => $this->user->id,
        ]);
        $this->post = Post::factory()->create([
            'team_id' => $this->team->id,
            'questionnaire_id' => $this->questionnaire->id,
        ]);
    }

    private function answer(array $overrides = []): array
    {
        return array_merge([
            'post_id' => $this->post->id,
            'questionnaire_id' => $this->questionnaire->id,
            'question_no' => 0,
            'answer' => '◯',
        ], $overrides);
    }

    public function test_アンケートに回答できる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer())
            ->assertStatus(200);

        $this->assertDatabaseHas('questionnaire_answers', [
            'questionnaire_id' => $this->questionnaire->id,
            'user_id' => $this->user->id,
            'question_no' => 0,
            'answer' => '◯',
        ]);
    }

    public function test_同じ設問に再回答すると上書きされる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer())
            ->assertStatus(200);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['answer' => '✕']))
            ->assertStatus(200);

        $this->assertSame(1, QuestionnaireAnswer::count());
        $this->assertDatabaseHas('questionnaire_answers', [
            'questionnaire_id' => $this->questionnaire->id,
            'question_no' => 0,
            'answer' => '✕',
        ]);
    }

    public function test_設問ごとに別々の回答を保持する(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['question_no' => 0, 'answer' => '◯']))
            ->assertStatus(200);
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['question_no' => 1, 'answer' => '△']))
            ->assertStatus(200);

        $this->assertSame(2, QuestionnaireAnswer::count());
    }

    public function test_回答削除で回答が消える(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer())
            ->assertStatus(200);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['answer' => '回答削除']))
            ->assertStatus(200);

        $this->assertSame(0, QuestionnaireAnswer::count());
    }

    public function test_他チームの投稿のアンケートには回答できない(): void
    {
        $otherPost = Post::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'questionnaire_id' => $this->questionnaire->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['post_id' => $otherPost->id]))
            ->assertStatus(404);
    }

    public function test_投稿とアンケートの対応が違う場合は404(): void
    {
        $other = Questionnaire::create([
            'title' => '別アンケート', 'items' => json_encode([['text' => 'a']]),
            'created_id' => $this->user->id, 'updated_id' => $this->user->id,
        ]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer(['questionnaire_id' => $other->id]))
            ->assertStatus(404);
    }

    public function test_アンケート付き投稿の詳細に集計結果が含まれる(): void
    {
        $this->actingAsTeamMember($this->user, $this->team)
            ->postJson('/api/questionnaires/answer', $this->answer())
            ->assertStatus(200);

        $response = $this->actingAsTeamMember($this->user, $this->team)
            ->getJson('/api/posts/' . $this->post->id)
            ->assertStatus(200);

        $this->assertSame('参加できますか', $response->json('questionnaire.title'));
        $this->assertSame(1, $response->json('questionnaire.items.0.answerCounts.◯'));
        $this->assertSame('◯', $response->json('questionnaire.items.0.myAnswer'));
    }
}
