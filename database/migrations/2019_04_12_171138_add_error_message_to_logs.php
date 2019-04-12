<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddErrorMessageToLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('logs', function (Blueprint $table) {
        $table->text('error_message')->after('user_agent')
          ->comment('エラーメッセージ')->nullable();
        $table->integer('created_id')->nullable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nullable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nullable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時')->default(\Illuminate\Support\Facades\DB::raw('now()'));
        $table->integer('user_id')->nullable()->change();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('logs', function (Blueprint $table) {
        $table->dropColumn('error_message');
        $table->dropColumn('created_id');
        $table->dropColumn('created_at');
        $table->dropColumn('updated_id');
        $table->dropColumn('updated_at');
      });
    }
}
