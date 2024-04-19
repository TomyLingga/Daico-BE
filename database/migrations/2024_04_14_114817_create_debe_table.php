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
            $table->foreignId('id_category3')->constrained('category3');
            $table->foreignId('id_m_report')->constrained('m_report');
            $table->foreignId('id_c_centre')->constrained('c_centre');
            $table->foreignId('id_plant')->nullable()->constrained('plant');
            $table->foreignId('id_allocation')->nullable()->constrained('allocation');
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
