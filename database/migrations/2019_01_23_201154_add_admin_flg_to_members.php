<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdminFlgToMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('members', function (Blueprint $table) {
        $table->boolean('admin_flg')->after('type')
          ->comment('管理者フラグ')->nullable();
      });
//      update members m join users u on m.name=u.name set m.user_id=u.id where type in (2,3)
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('members', function (Blueprint $table) {
        $table->dropColumn('admin_flg');
      });
    }
}
