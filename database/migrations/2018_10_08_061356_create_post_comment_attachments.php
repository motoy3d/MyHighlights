<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostCommentAttachments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('post_comment_attachments', function (Blueprint $table) {
        $table->increments('id')->comment('投稿コメント添付ID');
        $table->integer('post_comment_id')->comment('投稿コメントID');
        $table->string('original_file_name', 200)->comment('元ファイル名');
        $table->string('file_path', 200)->comment('ファイルパス');
        $table->string('file_type', 10)->comment('ファイルタイプ');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE post_comment_attachments COMMENT '投稿コメント添付'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('post_comment_attachments');
    }
}
