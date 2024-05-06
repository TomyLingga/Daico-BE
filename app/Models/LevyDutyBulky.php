<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class LevyDutyBulky extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'levy_duty_bulky';

    protected $fillable = [
        'id_bulky',
        'tanggal',
        'currency_id',
        'nilai'
    ];

    public function bulky()
    {
        return $this->belongsTo(MasterBulky::class, 'id_bulky');
    }
}
