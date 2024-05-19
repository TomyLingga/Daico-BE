<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class DailyDMO extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'dmo_daily';

    protected $fillable = [
        'tanggal',
        'value',
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
