<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class LaporanProduksi extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'laporan_produksi';

    protected $fillable = [
        'id_plant',
        'id_uraian',
        'tanggal',
        'value',
    ];

    public function uraian()
    {
        return $this->belongsTo(UraianProduksi::class, 'id_uraian');
    }

    public function kategoriUraian()
    {
        return $this->hasOneThrough(KategoriUraianProduksi::class, UraianProduksi::class, 'id', 'id', 'id_uraian', 'id_category');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'id_plant');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
