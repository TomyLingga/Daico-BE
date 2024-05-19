<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MonthlyDMO extends Model
{
    use HasFactory, Notifiable, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'dmo_monthly';

    protected $fillable = [
        'tanggal',
        'dmo',
        'cpo_olah_rkap',
        'pengali_kapasitas_utility',
        'kapasitas_utility',
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
