<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInitialSupplyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('initial_supply', function (Blueprint $table) {
            $table->id();
            $table->integer('productable_id');
            $table->string('productable_type');
            $table->date('tanggal');
            $table->decimal('qty', 30, 2);
            $table->decimal('harga', 30, 2);
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
        Schema::dropIfExists('initial_supply');
    }
}
