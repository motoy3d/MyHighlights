<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostComments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('post_comments', function (Blueprint $table) {
        $table->increments('id')->comment('投稿コメントID');
        $table->string('post_id')->comment('投稿ID');
        $table->string('user_id')->comment('ユーザーID');
        $table->text('comment_text')->comment('コメント');
        $table->text('like_user_ids')->nullable()->comment('いいねユーザーIDリスト');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE post_comments COMMENT '投稿コメント'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('post_comments');
    }
}
