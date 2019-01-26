<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserIdToMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('members', function (Blueprint $table) {
        $table->integer('user_id')->after('id')
          ->comment('ユーザーID')->nullable();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('members', function (Blueprint $table) {
        $table->dropColumn('user_id');
      });
    }
}
