<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIcalIdToTeams extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('teams', function (Blueprint $table) {
        $table->string('ical_id')->after('plan_id')
          ->comment('iCal ID')->nullable();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('teams', function (Blueprint $table) {
        $table->dropColumn('ical_id');
      });
    }
}
