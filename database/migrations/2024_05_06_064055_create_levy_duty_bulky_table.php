<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLevyDutyBulkyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('levy_duty_bulky', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_bulky')->constrained('master_bulky');
            $table->date('tanggal')->unique();
            $table->decimal('nilai', 30, 2);
            $table->integer('currency_id');
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
        Schema::dropIfExists('levy_duty_bulky');
    }
}
