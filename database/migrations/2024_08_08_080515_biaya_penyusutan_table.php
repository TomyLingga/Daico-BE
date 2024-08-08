<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BiayaPenyusutanTable extends Migration
{
    public function up()
    {
        Schema::create('biaya_penyusutan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alokasi_id')->constrained('allocation');
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
        Schema::dropIfExists('kapasitas_wh_pallet');
    }
}
