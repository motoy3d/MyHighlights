<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('members', function (Blueprint $table) {
        $table->increments('id')->comment('メンバーID');
        $table->string('name', 100)->comment('メンバー名');
        $table->string('type', 10)->comment('種別(選手,スタッフ,家族)');
        $table->date('birthday')->nullable()->comment('誕生日');
        $table->integer('team_id')->comment('チームID');
        $table->string('backno', 4)->comment('背番号');
        $table->boolean('has_profile_img_flg')->comment('プロフィール画像ありフラグ');
        $table->date('withdrawal_date')->nullable()->comment('退会日');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->softDeletes();
      });
      DB::statement("ALTER TABLE members COMMENT 'メンバー'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('members');
    }
}
