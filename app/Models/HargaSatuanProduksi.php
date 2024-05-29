<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class HargaSatuanProduksi extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'harga_satuan_produksi';

    protected $fillable = [
        'id_uraian_produksi',
        'value',
    ];

    public function uraian()
    {
        return $this->belongsTo(UraianProduksi::class, 'id_uraian_produksi');
    }

    public function kategoriUraian()
    {
        return $this->hasOneThrough(KategoriUraianProduksi::class, UraianProduksi::class, 'id', 'id', 'id_uraian_produksi', 'id_category');
    }

    public function laporanProduksi()
    {
        return $this->hasMany(LaporanProduksi::class, 'id_harga_satuan');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
