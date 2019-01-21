<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdminFlgToUserTeams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('user_teams', function (Blueprint $table) {
        $table->boolean('admin_flg')->after('team_id')
          ->comment('管理者フラグ')->nullable();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('user_teams', function (Blueprint $table) {
        $table->dropColumn('admin_flg');
      });
    }
}
