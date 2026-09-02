<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumMiddleware;

/**
 * Sanctum の stateful 判定ミドルウェア。SameSite の上書きだけを外したもの。
 *
 * 標準実装は stateful なAPIリクエストのたびに
 * session.same_site を 'lax' へ強制的に書き換える。
 * するとAPI応答でセッションクッキーが SameSite=Lax として再発行され、
 * LINE Notify のコールバック(response_mode=form_post による別サイトからのPOST)で
 * セッションクッキーがブラウザから送られなくなり、連携設定が失敗する。
 *
 * config/session.php で明示している値をそのまま使うため、上書きしない。
 * CSRFトークンの検証は Sanctum のパイプライン内で従来どおり行われる。
 */
class EnsureFrontendRequestsAreStateful extends SanctumMiddleware
{
    protected function configureSecureCookieSessions()
    {
        // セッションクッキーをjsから読めなくするのは維持する
        config(['session.http_only' => true]);
    }
}
