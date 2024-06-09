<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class KapasitasWhPallet extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'kapasitas_wh_pallet';

    protected $fillable = [
        'location_id',
        'tanggal',
        'value'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
