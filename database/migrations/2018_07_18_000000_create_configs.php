<?php

  use Illuminate\Support\Facades\Schema;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Database\Migrations\Migration;

  class CreateConfigs extends Migration
  {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('configs', function (Blueprint $table) {
        $table->increments('id')->comment('設定ID');
        $table->string('key', 50)->comment('設定キー');
        $table->string('value', 255)->comment('設定値');
        $table->string('memo', 255)->comment('メモ');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->unique('key');
      });
      DB::statement("ALTER TABLE configs COMMENT '設定'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('configs');
    }
  }
