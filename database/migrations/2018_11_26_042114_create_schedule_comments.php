<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScheduleComments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('schedule_comments', function (Blueprint $table) {
        $table->increments('id')->comment('予定コメントID');
        $table->string('schedule_id')->comment('予定ID');
        $table->string('user_id')->comment('ユーザーID');
        $table->text('comment_text')->comment('コメント');
        $table->text('like_user_ids')->nullable()->comment('いいねユーザーIDリスト');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE schedule_comments COMMENT '予定コメント'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('schedule_comments');
    }
}
