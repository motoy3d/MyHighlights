<?php

namespace App\Http\Middleware;

use App\Team;
use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * current_team_id クッキーが、ログイン中のユーザーの所属チームを指していることを保証する。
 *
 * 対象チームはこのクッキーで決まるが、jsから読む必要があるため
 * 暗号化対象外にしてあり、利用者がブラウザ側で任意の値に書き換えられる。
 * 各コントローラはクッキーの値をそのまま検索条件に使っているため、
 * ここで所属チームかどうかを検証しないと他チームのデータに触れてしまう。
 *
 * 不正な値や未設定の場合は、所属チームの1件目に是正して処理を続行する。
 * (HomeControllerが以前から行っている是正と同じ挙動。
 *  チームから外された利用者がタブを開いたままでもアプリが壊れないようにするため、
 *  エラーにはしない)
 */
class EnsureCurrentTeamIsOwn
{
    public const COOKIE_ID = 'current_team_id';
    public const COOKIE_NAME = 'current_team_name';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            // 認証はauthミドルウェア側の責務
            return $next($request);
        }

        /** @var \Illuminate\Support\Collection<int, Team> $teams */
        $teams = $user->teams()->get();
        if ($teams->isEmpty()) {
            // 所属チームが無い状態でAPIを叩くことはない
            abort(403, '所属しているチームがありません。');
        }

        $current = $request->cookie(self::COOKIE_ID);
        if ($current !== null && $teams->contains('id', (int) $current)) {
            return $next($request);
        }

        $this->switchTo($request, $teams->first());

        return $next($request);
    }

    /**
     * 以降の処理とブラウザの両方で参照するチームを差し替える。
     */
    private function switchTo(Request $request, Team $team): void
    {
        // Cookie::get() はリクエストのクッキーを読むため、こちらも書き換える
        $request->cookies->set(self::COOKIE_ID, (string) $team->id);
        $request->cookies->set(self::COOKIE_NAME, (string) $team->name);

        $minutes = (int) config('session.lifetime');
        $secure = app()->environment('production');
        $httpOnly = false; // jsで扱うために必要

        Cookie::queue(Cookie::make(self::COOKIE_ID, (string) $team->id,
            $minutes, '/', '', $secure, $httpOnly));
        Cookie::queue(Cookie::make(self::COOKIE_NAME, (string) $team->name,
            $minutes, '/', '', $secure, $httpOnly));
    }
}
