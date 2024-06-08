<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStokBulkyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stok_bulky', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->nullable()->constrained('tank');
            $table->date('tanggal');
            $table->integer('productable_id');
            $table->string('productable_type');
            $table->decimal('stok_mt', 30, 2);
            $table->decimal('stok_exc_btm_mt', 30, 2);
            $table->decimal('umur', 30, 2);
            $table->string('remarks');

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
        Schema::dropIfExists('stok_bulky');
    }
}
