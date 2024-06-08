<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class InitialSupply extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'initial_supply';

    protected $fillable = [
        'productable_id',
        'productable_type',
        'tanggal',
        'qty',
        'harga'
    ];

    public function productable()
    {
        return $this->morphTo();
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
