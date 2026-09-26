<?php

namespace App\Support;

use App\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 例外をlogsテーブルに記録する。
 *
 * 旧 App\Exceptions\Handler::report() の処理を切り出したもの。
 * 記録自体に失敗しても元の例外処理を妨げないよう、失敗は握り潰す。
 */
class ExceptionLogger
{
    public static function record(Throwable $e): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            $path = $_SERVER['REQUEST_URI'] ?? '';
            $content = $path ? $method . ' ' . $path : '';

            Log::create([
                'log_timestamp' => DB::raw('now()'),
                'level' => 'error',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ipaddress' => $_SERVER['REMOTE_ADDR'] ?? '',
                'content' => $content,
                'error_message' => $e->getMessage(),
                'user_id' => Auth::id(),
                'created_id' => Auth::id(),
                'updated_id' => Auth::id(),
            ]);
        } catch (Throwable $ignored) {
            // DBに書けない状況(接続断など)でハンドラ自身が落ちると
            // 元の例外が握り潰されるため、ここでは何もしない。
        }
    }
}
