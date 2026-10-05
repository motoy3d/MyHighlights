<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 送った通知の記録を、アプリ内のお知らせ一覧(🔔。#125)にも使えるよう広げる。
 *
 * - スマホに送らなかった通知(購読の無い人の分)も入るので、名前を push_notices から notices に変える
 * - 一覧に出すチーム・種類・題名・本文と、「開いた」時刻を足す
 * - users.push_seen_at は「お知らせ一覧を最後に開いた時刻」の意味に変わる(列はそのまま)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('push_notices', 'notices');

        Schema::table('notices', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id')->comment('どのチームの通知か');
            $table->string('type', 32)->default('')->after('team_id')->comment('new_post / post_comment / schedule_change など');
            $table->string('title', 255)->default('')->after('tag')->comment('題名(チーム名)');
            $table->string('body', 500)->default('')->after('title')->comment('本文');
            $table->dateTime('opened_at', 3)->nullable()->after('url')->comment('開いた時刻(NULL はまだ開いていない)');
            // その投稿を開いたときに、その投稿についての通知をまとめて開いたにする
            $table->index(['user_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'tag']);
            $table->dropColumn(['team_id', 'type', 'title', 'body', 'opened_at']);
        });
        Schema::rename('notices', 'push_notices');
    }
};
