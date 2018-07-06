<?php

  use Illuminate\Support\Facades\Schema;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Database\Migrations\Migration;

  class CreatePasswordResetsTable extends Migration
  {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('password_resets', function (Blueprint $table) {
        $table->string('email', 100)->index()->comment('メールアドレス');
        $table->string('token')->comment('トークン');
        $table->dateTime('created_at')->nullable()->comment('作成日時');
      });
      DB::statement("ALTER TABLE password_resets COMMENT 'パスワードリセット'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('password_resets');
    }
  }
