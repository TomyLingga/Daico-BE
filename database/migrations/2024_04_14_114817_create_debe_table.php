<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDebeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('debe', function (Blueprint $table) {
            $table->id();
            $table->string('coa');
            $table->foreignId('id_category3')->constrained('category3')->nullable();
            $table->foreignId('id_m_report')->constrained('m_report')->nullable();
            $table->foreignId('id_c_centre')->constrained('c_centre')->nullable();
            $table->foreignId('id_plant')->constrained('plant')->nullable();
            $table->foreignId('id_allocation')->constrained('allocation')->nullable();
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
        Schema::dropIfExists('debe');
    }
}
