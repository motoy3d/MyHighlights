<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuetionnaireAnswers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('questionnaire_answers', function (Blueprint $table) {
        $table->increments('id')->comment('アンケート回答ID');
        $table->integer('questionnaire_id')->comment('アンケートID');
        $table->integer('user_id')->comment('ユーザーID');
        $table->integer('question_no')->comment('質問No(questionnairesテーブルのitemsカラムの順番と紐づく)');
        $table->string('answer', 4)->comment('回答(◯,△,✕)');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
      });
      DB::statement("ALTER TABLE questionnaire_answers COMMENT 'アンケート回答'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('questionnaire_answers');
    }
}
