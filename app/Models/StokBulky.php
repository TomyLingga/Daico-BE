<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class StokBulky extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'stok_bulky';

    protected $fillable = [
        'tanggal',
        'tank_id',
        'productable_id',
        'productable_type',
        'stok_mt',
        'stok_exc_btm_mt',
        'umur',
        'remarks'
    ];

    public function productable()
    {
        return $this->morphTo();
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    protected $appends = ['extended_productable'];

    public function getExtendedProductableAttribute()
    {
        if ($this->productable_type === MasterProduct::class) {
            return $this->productable->load('productable');
        }

        if ($this->productable_type === MasterSubProduct::class) {
            return $this->productable->load('product.productable');
        }

        return $this->productable;
    }
}
