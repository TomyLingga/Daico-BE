<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterProduct extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_product';

    protected $fillable = [
        'productable_id',
        'productable_type',
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function productable()
    {
        return $this->morphTo();
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function subProduct()
    {
        return $this->hasMany(MasterSubProduct::class, 'product_id');
    }

    public function initialSupply()
    {
        return $this->morphOne(InitialSupply::class, 'productable');
    }
}
