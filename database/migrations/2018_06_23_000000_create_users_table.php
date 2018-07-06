<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('users', function (Blueprint $table) {
        $table->increments('id')->comment('ユーザーID');
        $table->string('name', 100)->comment('ユーザー名');
        $table->string('email', 100)->unique()->comment('メールアドレス');
        $table->string('password', 100)->comment('パスワード');
        $table->integer('team_id')->nullable()->comment('チームID');
        $table->integer('member_id')->nullable()->comment('メンバーID');
        $table->date('withdrawal_date')->nullable()->comment('退会日');
        $table->rememberToken();
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE users COMMENT 'ユーザー'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
