<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHolidays extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('holidays', function (Blueprint $table) {
        $table->increments('id')->comment('祝日ID');
        $table->date('holiday_date')->comment('祝日日付');
        $table->string('name', 20)->comment('祝日名称');
        $table->integer('created_id')->nulable()->comment('登録ユーザーID');
        $table->dateTime('created_at')->nulable()->default(null)->comment('登録日時');
        $table->integer('updated_id')->nulable()->comment('更新ユーザーID');
        $table->timestamp('updated_at')->comment('更新日時');
        $table->unique(['holiday_date']);
      });
      DB::statement("ALTER TABLE holidays COMMENT '祝日'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('holidays');
    }
}
