<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class cpoKpbn extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'cpo_kpbn';

    protected $fillable = [
        'tanggal',
        'avg'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
