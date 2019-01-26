<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropUserTeams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::drop('user_teams');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::create('user_teams', function (Blueprint $table) {
        $table->increments('id')->comment('ID');
        $table->integer('user_id')->comment('ユーザーID');
        $table->integer('team_id')->comment('チームID');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE user_teams COMMENT 'ユーザー・チーム'");
    }
}
