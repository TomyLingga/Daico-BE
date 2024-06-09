<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Tank extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'tank';

    protected $fillable = [
        'location_id',
        'name',
        'capacity'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function stokBulky()
    {
        return $this->hasMany(StokBulky::class, 'tank_id');
    }
}
