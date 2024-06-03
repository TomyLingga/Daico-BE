<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTambahanToActualInCpoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('actual_in_cpo', function (Blueprint $table) {
            $table->decimal('qty_out', 30, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('actual_in_cpo', function (Blueprint $table) {
            $table->dropColumn(['qty_out']);
        });
    }
}
