<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterSubProduct extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_sub_product';

    protected $fillable = [
        'product_id',
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function product()
    {
        return $this->belongsTo(MasterProduct::class, 'product_id');
    }

    public function initialSupply()
    {
        return $this->morphOne(InitialSupply::class, 'productable');
    }

    public function stokBulky()
    {
        return $this->morphOne(StokBulky::class, 'productable');
    }
}
