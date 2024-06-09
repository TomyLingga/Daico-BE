<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStokRetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stok_retail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained('location');
            $table->date('tanggal');
            $table->integer('productable_id');
            $table->string('productable_type');
            $table->decimal('ctn', 30, 2);
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
        Schema::dropIfExists('stok_retail');
    }
}
