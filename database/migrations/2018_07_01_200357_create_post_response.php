<?php

  use Illuminate\Support\Facades\Schema;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Database\Migrations\Migration;

  class CreatePostResponse extends Migration
  {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('post_response', function (Blueprint $table) {
        $table->integer('user_id')->comment('ユーザーID');
        $table->integer('post_id')->comment('投稿ID');
        $table->boolean('read_flg')->default(false)->comment('既読フラグ');
        $table->boolean('like_flg')->default(false)->comment('いいねフラグ');
        $table->boolean('star_flg')->default(false)->comment('スターフラグ');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->primary(['user_id', 'post_id']);
      });
      DB::statement("ALTER TABLE post_response COMMENT '投稿レスポンス'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('post_response');
    }
  }
