<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePosts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('posts', function (Blueprint $table) {
        $table->increments('id')->comment('投稿ID');
        $table->integer('team_id')->comment('チームID');
        $table->string('title', 50)->comment('タイトル');
        $table->text('content')->comment('本文');
        $table->integer('category_id')->nullable()->comment('カテゴリID');
        $table->integer('quetionnaire_id')->nullable()->comment('アンケートID');
        $table->boolean('notification_flg')->comment('通知フラグ');
        $table->integer('likes_count')->default(0)->comment('いいね数');
        $table->integer('star_count')->default(0)->comment('スター数');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE posts COMMENT '投稿'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('posts');
    }
}
