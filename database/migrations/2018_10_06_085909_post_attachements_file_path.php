<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

  /**
   * カラム名変更。file_name→file_path
   * Class PostAttachementsFilePath
   */
class PostAttachementsFilePath extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('post_attachments', function (Blueprint $table) {
        $table->renameColumn('file_name', 'file_path')
          ->comment('ファイルパス');
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
        $table->renameColumn('file_path', 'file_name')
          ->comment('ファイル名');
      });
    }
}
