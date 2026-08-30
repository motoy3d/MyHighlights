<?php

namespace App\Providers;

use DateTimeInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query) {
            Log::info($this->interpolateBindings($query));
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * ログ出力用に、プレースホルダへバインド値を埋め込んだSQLを組み立てる。
     *
     * バインド値にはDateTimeやnull、バイナリも入り得るため、
     * preg_replace()に渡す前に必ず文字列へ変換する。
     */
    private function interpolateBindings(QueryExecuted $query): string
    {
        $sql = $query->sql;

        foreach ($query->bindings as $binding) {
            $sql = preg_replace('/\?/', $this->stringifyBinding($binding), $sql, 1);
        }

        return $sql;
    }

    private function stringifyBinding(mixed $binding): string
    {
        $value = match (true) {
            $binding === null => 'null',
            is_bool($binding) => $binding ? '1' : '0',
            $binding instanceof DateTimeInterface => $binding->format('Y-m-d H:i:s'),
            is_object($binding) && method_exists($binding, '__toString') => (string) $binding,
            is_scalar($binding) => (string) $binding,
            default => gettype($binding),
        };

        // 置換後の文字列に含まれる "$" や "\" がpreg_replaceの後方参照として
        // 解釈されないようにエスケープする。
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }
}
