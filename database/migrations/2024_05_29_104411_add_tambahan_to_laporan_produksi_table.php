<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTambahanToLaporanProduksiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('laporan_produksi', function (Blueprint $table) {
            $table->foreignId('id_harga_satuan')->nullable()->constrained('harga_satuan_produksi');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('laporan_produksi', function (Blueprint $table) {
            $table->dropColumn(['id_harga_satuan']);
        });
    }
}
