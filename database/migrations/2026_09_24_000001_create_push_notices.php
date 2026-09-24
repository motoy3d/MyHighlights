<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 送ったプッシュ通知の記録と、最後にアプリを開いた時刻(#123)。
 *
 * - アイコンのバッジ：まだ見ていないお知らせ = push_seen_at より後に送った通知の数
 * - #110 の「消えた通知から開く」：前面に戻ったときに、バックグラウンドの間に送った通知を返す
 *   (これまでキャッシュに置いていたが、デプロイで消えるため表に移した)
 * 30日より前の行は、送るときにその人の分を消す(App\Support\PushNoticeLog)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('送った相手');
            $table->string('nid', 32)->comment('通知ごとの目印');
            $table->string('tag', 64)->comment('post-123 など');
            $table->string('url', 255)->comment('開く画面');
            // 「消えた通知から開く」はミリ秒で比べるので、ミリ秒まで持つ
            $table->dateTime('created_at', 3)->comment('送った時刻');
            $table->index(['user_id', 'created_at']);
        });

        DB::statement("ALTER TABLE push_notices COMMENT '送ったプッシュ通知'");

        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('push_seen_at', 3)->nullable()->after('push_prefs')
                ->comment('最後にアプリを開いた時刻(これより後の通知がバッジの数)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_seen_at');
        });
        Schema::dropIfExists('push_notices');
    }
};
