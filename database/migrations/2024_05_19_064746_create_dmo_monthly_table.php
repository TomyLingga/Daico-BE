<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDmoMonthlyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dmo_monthly', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->decimal('dmo', 30, 2);
            $table->decimal('cpo_olah_rkap', 30, 2);
            $table->decimal('kapasitas_utility', 30, 2);
            $table->decimal('pengali_kapasitas_utility', 30, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dmo_monthly');
    }
}
