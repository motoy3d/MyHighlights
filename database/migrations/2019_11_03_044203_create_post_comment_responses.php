<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * 投稿コメントに対する既読やいいねのデータを格納するテーブルを作成
 */
class CreatePostCommentResponses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('post_comment_responses', function (Blueprint $table) {
        $table->increments('id')->comment('投稿コメントレスポンスID');
        $table->integer('user_id')->comment('ユーザーID');
        $table->integer('post_comment_id')->comment('投稿コメントID');
        $table->boolean('read_flg')->default(false)->comment('既読フラグ');
        $table->boolean('like_flg')->default(false)->comment('いいねフラグ');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->unique(['user_id', 'post_comment_id']);
      });
      DB::statement("ALTER TABLE post_comment_responses COMMENT '投稿コメントレスポンス'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('post_comment_responses');
    }
}
