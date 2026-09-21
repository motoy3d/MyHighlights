<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プッシュ通知の種類ごとのオン・オフ(#110)。
 *
 * 種類が増えてもマイグレーションが要らないようJSONの1列で持つ。
 * NULLや欠けているキーは User::PUSH_PREF_DEFAULTS の既定値として扱う。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'push_prefs')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('push_prefs')->nullable()->after('mail_notification_flg')
                ->comment('プッシュ通知の種類ごとの設定');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_prefs');
        });
    }
};
