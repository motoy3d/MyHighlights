<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PostDrop2cols extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('posts', function (Blueprint $table) {
        $table->dropColumn('likes_count', 'star_count');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('posts', function (Blueprint $table) {
        $table->integer('likes_count')->after('comment_count')
          ->comment('いいね数')->default(0);
        $table->integer('star_count')->after('likes_count')
          ->comment('お気に入り数')->default(0);
      });
    }
}
