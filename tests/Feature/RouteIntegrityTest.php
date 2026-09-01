<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ルート定義の健全性を検査する。
 *
 * Route::resource() は実装が無いアクションのルートも生成してしまうため、
 * 呼ぶと500になる「死んだルート」が混入しやすい。
 */
class RouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_全ルートが実在するコントローラメソッドを指している(): void
    {
        $dead = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! str_starts_with($class, 'App\\Http\\Controllers')) {
                continue;
            }
            if (! method_exists($class, $method)) {
                $dead[] = $route->methods()[0] . ' /' . $route->uri() . ' -> ' . $action;
            }
        }

        $this->assertSame([], $dead,
            "実装が無いメソッドを指すルートがある（呼ぶと500になる）:\n" . implode("\n", $dead));
    }

    /**
     * 塞いだリソースルートが復活していないこと。
     */
    public function test_実装の無いリソースルートが生成されていない(): void
    {
        $closed = [
            'api/posts/{post}/edit',
            'api/schedules/{schedule}',
            'api/schedules/{schedule}/edit',
            'api/members/create',
            'api/members/{member}/edit',
        ];

        $uris = [];
        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $uris[] = $route->uri();
            }
        }

        foreach ($closed as $uri) {
            $this->assertNotContains($uri, $uris, "{$uri} は塞いだはずのルート");
        }
    }

    /**
     * SPAが実際に叩いているエンドポイントが存在すること。
     */
    public function test_SPAが使うエンドポイントが揃っている(): void
    {
        $required = [
            'GET api/me', 'GET api/posts', 'POST api/posts', 'GET api/posts/create',
            'GET api/posts/search_init', 'GET api/posts/{post}', 'PUT api/posts/{post}',
            'DELETE api/posts/{post}',
            'GET api/schedules', 'POST api/schedules', 'GET api/schedules/create',
            'PUT api/schedules/{schedule}', 'DELETE api/schedules/{schedule}',
            'GET api/members', 'POST api/members', 'GET api/members/{member}',
            'PUT api/members/{member}', 'DELETE api/members/{member}',
            'POST api/post_comments/{post_id}', 'DELETE api/post_comments/{post_id}/{comment_id}',
            'POST api/post_responses/{post_id}', 'POST api/post_comment_responses/{post_comment_id}',
            'DELETE api/post_attachments/{post_attachment_id}',
            'GET api/schedule_comments/{schedule_id}', 'POST api/schedule_comments/{schedule_id}',
            'DELETE api/schedule_comments/{schedule_id}/{comment_id}',
            'POST api/questionnaires/answer', 'GET api/blog', 'GET api/ical/config',
            'POST api/users/updateName', 'POST api/users/updateNameKana',
            'POST api/users/updateEmail', 'POST api/users/updatePassword',
            'POST api/users/updateMailNotificationFlg', 'POST api/users/updateLINENotificationFlg',
        ];

        $existing = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $m) {
                $existing[] = $m . ' ' . $route->uri();
            }
        }

        foreach ($required as $r) {
            $this->assertContains($r, $existing, "SPAが使う {$r} が存在しない");
        }
    }
}
