<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameKanaUsersAndMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('users', function (Blueprint $table) {
        $table->string('name_kana')->after('name')
          ->comment('氏名かな')->nullable();
      });
      Schema::table('members', function (Blueprint $table) {
        $table->string('name_kana')->after('name')
          ->comment('氏名かな')->nullable();
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
        $table->dropColumn('name_kana');
      });
      Schema::table('members', function (Blueprint $table) {
        $table->dropColumn('name_kana');
      });
    }
}
