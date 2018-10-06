<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PostAttachementsAddFileName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('post_attachments', function (Blueprint $table) {
        $table->string('original_file_name', 200)->after('post_id')
          ->comment('元ファイル名');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('post_attachments', function (Blueprint $table) {
        $table->dropColumn('original_file_name');
      });
    }
}
