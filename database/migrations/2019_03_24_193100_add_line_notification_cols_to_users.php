<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLineNotificationColsToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('users', function (Blueprint $table) {
        $table->boolean('line_notification_flg')->after('mail_notification_flg')
          ->comment('LINE通知フラグ')->nullable()->default(0);
        $table->string('line_access_token')->after('line_notification_flg')
          ->comment('LINEアクセストークン')->nullable();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('line_notification_flg');
        $table->dropColumn('line_access_token');
      });
    }
}
