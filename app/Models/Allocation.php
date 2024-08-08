<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'allocation';

    protected $fillable = [
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function debe()
    {
        return $this->hasMany(Debe::class, 'id_allocation');
    }

    public function biayaPenyusutan()
    {
        return $this->hasMany(BiayaPenyusutan::class, 'alokasi_id');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
