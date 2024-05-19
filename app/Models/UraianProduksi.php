<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class UraianProduksi extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'uraian_produksi';

    protected $fillable = [
        'id_category',
        'nama',
        'satuan',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function laporanProduksi()
    {
        return $this->hasMany(LaporanProduksi::class, 'id_uraian');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriUraianProduksi::class, 'id_category');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
