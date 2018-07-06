<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('logs', function (Blueprint $table) {
        $table->increments('id')->comment('ログID');
        $table->timestamp('log_timestamp')->comment('ログ日時');
        $table->string('level', 10)->comment('ログレベル');
        $table->text('content')->comment('ログ内容');
        $table->integer('user_id')->default(0)->comment('ユーザーID');
        $table->string('ipaddress', 20)->nullable()->comment('IPアドレス');
        $table->string('user_agent', 255)->nullable()->comment('ユーザーエージェント');
      });
      DB::statement("ALTER TABLE logs COMMENT 'ログ'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('logs');
    }
}
