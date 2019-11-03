<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLikeCountToPostComments extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('post_comments', function (Blueprint $table) {
        $table->dropColumn('like_user_ids');
        $table->integer('like_count')->default(0)->comment('いいね数')->after('comment_text');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('post_comments', function (Blueprint $table) {
        $table->text('like_user_ids')->nullable()->comment('いいねユーザーIDリスト')->after('comment_text');
        $table->dropColumn('like_count');
      });
    }
}
