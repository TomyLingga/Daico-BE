<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterRekeningUnitKerjaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_rekening_unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekening_id')->nullable()->constrained('master_rekening');
            $table->date('tanggal');
            $table->decimal('value', 30, 2);
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
        Schema::dropIfExists('master_rekening_unit_kerja');
    }
}
