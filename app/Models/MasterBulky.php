<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterBulky extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_bulky';

    protected $fillable = [
        'name'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function marketRouters()
    {
        return $this->hasMany(MarketRoutersBulky::class, 'id_bulky');
    }

    public function levyDuty()
    {
        return $this->hasMany(LevyDutyBulky::class, 'id_bulky');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function product()
    {
        return $this->morphOne(MasterProduct::class, 'productable');
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
