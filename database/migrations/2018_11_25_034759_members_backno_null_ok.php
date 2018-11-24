<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

  /**
   * backnoカラムにNULLを許容
   * Class MembersBacknoNullOk
   */
class MembersBacknoNullOk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('members', function (Blueprint $table) {
        //
        $table->integer('backno')->nullable()->change();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('members', function (Blueprint $table) {
        DB::statement('UPDATE `members` SET `backno` = 0 WHERE `backno` IS NULL');
        $table->text('backno')->nullable(false)->change();
      });
    }
}
