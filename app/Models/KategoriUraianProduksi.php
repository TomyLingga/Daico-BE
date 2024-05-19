<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class KategoriUraianProduksi extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'kategori_uraian_produksi';

    protected $fillable = [
        'nama',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function uraian()
    {
        return $this->hasMany(UraianProduksi::class, 'id_category');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
