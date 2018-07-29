<?php

  use Illuminate\Support\Facades\Schema;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Database\Migrations\Migration;

  class CreateSchedule extends Migration
  {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('schedules', function (Blueprint $table) {
        $table->increments('id')->comment('予定ID');
        $table->integer('team_id')->comment('チームID');
        $table->date('schedule_date')->comment('日付');
        $table->string('title', 50)->comment('タイトル');
        $table->boolean('allday_flg')->comment('終日フラグ');
        $table->time('time_from')->nullable()->comment('開始時刻');
        $table->time('time_to')->nullable()->comment('終了時刻');
        $table->integer('category_id')->nullable()->comment('カテゴリID');
        $table->text('content')->nullable()->comment('詳細内容');
        $table->boolean('notification_flg')->default(false)->comment('通知フラグ');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->unique(["team_id", "schedule_date", "title"]);
      });
      DB::statement("ALTER TABLE schedules COMMENT '予定'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('schedules');
    }
  }
