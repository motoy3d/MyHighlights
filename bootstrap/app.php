<?php

use App\Http\Middleware\LogOperations;
use App\Support\ExceptionLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 旧 app/Http/Kernel.php の設定をそのまま移設したもの。

        // jsから読む必要があるためこの2つは暗号化しない
        $middleware->encryptCookies(except: [
            'current_team_id',
            'current_team_name',
        ]);

        $middleware->trimStrings(except: [
            'password',
            'password_confirmation',
        ]);

        // LINE Notifyからのコールバックはトークンを持たないため除外
        $middleware->validateCsrfTokens(except: [
            'line_auth',
        ]);

        // 同一オリジンのSPAからのAPIリクエストをセッション認証で通す(旧Passportの
        // CreateFreshApiTokenミドルウェアの代替)。SANCTUM_STATEFUL_DOMAINSを参照する。
        $middleware->statefulApi();
        $middleware->throttleApi('60,1');

        $middleware->alias([
            'log' => LogOperations::class,
        ]);

        // ALB等のリバースプロキシを前段に置く場合は下記を有効化する。
        // $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 旧 app/Exceptions/Handler.php の report() 相当。
        $exceptions->report(function (Throwable $e) {
            ExceptionLogger::record($e);
        });
    })->create();
