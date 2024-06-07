<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterRekeningTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_rekening', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nomor');
            $table->foreignId('matauang_id');
            $table->foreignId('jenis_id')->nullable()->constrained('master_jenis_rekening');
            $table->foreignId('tipe_id')->nullable()->constrained('master_tipe_cash_on_hand');
            $table->string('keterangan');
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
        Schema::dropIfExists('master_rekening');
    }
}
