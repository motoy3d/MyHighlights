<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.push_seen_at を消す(#125)。
 *
 * 🔔とアイコンの数を「お知らせ一覧を最後に開いた後に届いた通知の数」から
 * 「まだ開いていない通知の数」(notices.opened_at が NULL の件数)に変えたため、使わなくなった。
 * 一覧を開いただけで数が 0 になり、まだ見ていない通知が残っているのに分からなくなったため(2026-09-24 実機)。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'push_seen_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('push_seen_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('push_seen_at', 3)->nullable()->after('push_prefs');
        });
    }
};
